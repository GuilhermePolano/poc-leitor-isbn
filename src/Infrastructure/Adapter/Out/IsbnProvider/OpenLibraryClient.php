<?php
declare(strict_types=1);

namespace App\Infrastructure\Adapter\Out\IsbnProvider;

use App\Domain\Entity\DadosBibliograficos;
use App\Domain\Port\Out\IsbnProvider;
use App\Domain\Service\NormalizadorDeLivro;
use App\Domain\ValueObject\ISBN;
use App\Domain\ValueObject\Preco;

final class OpenLibraryClient implements IsbnProvider
{
    public function __construct(
        private readonly HttpClient $http,
        private readonly NormalizadorDeLivro $normalizador,
    ) {}

    public function nome(): string
    {
        return 'open_library';
    }

    public function buscarPorIsbn(ISBN $isbn): ?DadosBibliograficos
    {
        $url = 'https://openlibrary.org/isbn/' . urlencode($isbn->isbn13()) . '.json';
        $res = $this->http->get($url);
        if ($res['status'] !== 200 || !is_array($res['json'])) {
            return null;
        }
        $j = $res['json'];

        // Resolver nome dos autores (vem como referência {"key": "/authors/OL...A"})
        $autoresNomes = [];
        if (isset($j['authors']) && is_array($j['authors'])) {
            foreach ($j['authors'] as $autorRef) {
                if (isset($autorRef['key'])) {
                    $autorRes = $this->http->get('https://openlibrary.org' . $autorRef['key'] . '.json');
                    if ($autorRes['status'] === 200 && isset($autorRes['json']['name'])) {
                        $autoresNomes[] = (string) $autorRes['json']['name'];
                    }
                }
            }
        }

        $sinopse = null;
        if (isset($j['description'])) {
            $sinopse = is_array($j['description'])
                ? ($j['description']['value'] ?? null)
                : (string) $j['description'];
        }

        $idioma = null;
        if (isset($j['languages']) && is_array($j['languages']) && isset($j['languages'][0]['key'])) {
            // ex.: "/languages/por" → "por"
            $idioma = str_replace('/languages/', '', (string) $j['languages'][0]['key']);
        }

        $ano = null;
        if (isset($j['publish_date']) && preg_match('/(\d{4})/', (string) $j['publish_date'], $m)) {
            $ano = (int) $m[1];
        }

        $dim = $this->normalizador->dimensoesFromTexto($j['physical_dimensions'] ?? null);

        $capa = null;
        $thumb = null;
        if (isset($j['covers']) && is_array($j['covers']) && isset($j['covers'][0])) {
            $coverId = (int) $j['covers'][0];
            $capa  = "https://covers.openlibrary.org/b/id/{$coverId}-L.jpg";
            $thumb = "https://covers.openlibrary.org/b/id/{$coverId}-M.jpg";
        } else {
            // Fallback: capa por ISBN
            $capa  = 'https://covers.openlibrary.org/b/isbn/' . urlencode($isbn->isbn13()) . '-L.jpg';
            $thumb = 'https://covers.openlibrary.org/b/isbn/' . urlencode($isbn->isbn13()) . '-M.jpg';
        }

        return new DadosBibliograficos(
            isbn13: $isbn->isbn13(),
            isbn10: $isbn->isbn10(),
            titulo: (string) ($j['title'] ?? ''),
            subtitulo: $j['subtitle'] ?? null,
            autores: $autoresNomes,
            editora: isset($j['publishers'][0]) ? (string) $j['publishers'][0] : null,
            anoPublicacao: $ano,
            dataPublicacao: $j['publish_date'] ?? null,
            idioma: $idioma,
            paginas: isset($j['number_of_pages']) ? (int) $j['number_of_pages'] : null,
            sinopse: $sinopse,
            assuntos: (array) ($j['subjects'] ?? []),
            categorias: [],
            formato: null,
            dimensoes: $dim,
            peso: $j['weight'] ?? null,
            preco: new Preco(),
            localPublicacao: isset($j['publish_places'][0]['name']) ? (string) $j['publish_places'][0]['name'] : null,
            capaUrl: $capa,
            capaThumbnail: $thumb,
            linkPreview: isset($j['key']) ? 'https://openlibrary.org' . $j['key'] : null,
            avaliacaoMedia: null,
            qtdAvaliacoes: null,
            fonteApi: $this->nome(),
            providerOrigem: 'open_library',
            consultadoEm: date('c'),
            payloadBruto: $j,
        );
    }
}
