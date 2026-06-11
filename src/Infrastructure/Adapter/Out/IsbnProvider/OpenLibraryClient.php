<?php
declare(strict_types=1);

namespace App\Infrastructure\Adapter\Out\IsbnProvider;

use App\Domain\Entity\DadosBibliograficos;
use App\Domain\Port\Out\IsbnProvider;
use App\Domain\Service\IdiomaNormalizer;
use App\Domain\Service\NormalizadorDeLivro;
use App\Domain\ValueObject\ISBN;
use App\Domain\ValueObject\Preco;

final class OpenLibraryClient implements IsbnProvider
{
    public function __construct(
        private readonly HttpClient $http,
        private readonly NormalizadorDeLivro $normalizador,
        private readonly IdiomaNormalizer $idiomaNormalizer,
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

        $idiomaBruto = null;
        if (isset($j['languages']) && is_array($j['languages']) && isset($j['languages'][0]['key'])) {
            // ex.: "/languages/por" → "por"
            $idiomaBruto = str_replace('/languages/', '', (string) $j['languages'][0]['key']);
        }
        $idioma = $this->idiomaNormalizer->normalizar($idiomaBruto);

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

        // ---- Extra E1: enriquecimento ----
        $contributors    = $this->extrairContributors($j);
        $physicalFormat  = $j['physical_format'] ?? null;
        $editionName     = $j['edition_name']    ?? null;
        $series          = $this->extrairSeries($j);

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
            formato: $physicalFormat,
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
            // ---- Extra E1 ----
            contributors: $contributors,
            maturityRating: null,
            mainCategory: null,
            physicalFormat: $physicalFormat,
            editionName: is_string($editionName) ? $editionName : null,
            series: $series,
        );
    }

    /**
     * Open Library expõe contribuidores em formatos variados:
     *  - 'contributors' como array de objetos {role, name} OU array de strings
     *  - 'translators', 'illustrators' como arrays separados em algumas edições
     *
     * Devolvemos: [['role' => 'translator'|'illustrator'|'editor'|<outro>, 'name' => 'X'], ...]
     *
     * @return array<int, array{role:string,name:string}>
     */
    private function extrairContributors(array $j): array
    {
        $out = [];

        // contributors[]
        if (isset($j['contributors']) && is_array($j['contributors'])) {
            foreach ($j['contributors'] as $c) {
                if (is_array($c)) {
                    $role = $c['role'] ?? null;
                    $name = $c['name'] ?? null;
                    if ($name !== null && $name !== '') {
                        $out[] = [
                            'role' => $this->normalizarRole(is_string($role) ? $role : 'contributor'),
                            'name' => (string) $name,
                        ];
                    }
                } elseif (is_string($c) && $c !== '') {
                    $out[] = ['role' => 'contributor', 'name' => $c];
                }
            }
        }

        // translators[] (alguns formatos legados / records)
        foreach (['translators' => 'translator', 'illustrators' => 'illustrator', 'editors' => 'editor'] as $campo => $roleCanonico) {
            if (isset($j[$campo]) && is_array($j[$campo])) {
                foreach ($j[$campo] as $pessoa) {
                    if (is_array($pessoa) && isset($pessoa['name'])) {
                        $out[] = ['role' => $roleCanonico, 'name' => (string) $pessoa['name']];
                    } elseif (is_string($pessoa) && $pessoa !== '') {
                        $out[] = ['role' => $roleCanonico, 'name' => $pessoa];
                    }
                }
            }
        }

        return $out;
    }

    private function normalizarRole(string $role): string
    {
        $r = strtolower(trim($role));
        return match (true) {
            str_contains($r, 'translat') || str_contains($r, 'tradu')  => 'translator',
            str_contains($r, 'illustrat') || str_contains($r, 'ilustr') => 'illustrator',
            str_contains($r, 'editor') || str_contains($r, 'edição') || str_contains($r, 'edicao') => 'editor',
            default => $role,
        };
    }

    /**
     * Algumas respostas trazem 'series' como string única, outras como array.
     */
    private function extrairSeries(array $j): ?string
    {
        if (!isset($j['series'])) {
            return null;
        }
        $s = $j['series'];
        if (is_string($s) && $s !== '') {
            return $s;
        }
        if (is_array($s) && isset($s[0])) {
            return is_string($s[0]) ? $s[0] : null;
        }
        return null;
    }
}
