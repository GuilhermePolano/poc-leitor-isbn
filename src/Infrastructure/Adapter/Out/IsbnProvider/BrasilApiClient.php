<?php
declare(strict_types=1);

namespace App\Infrastructure\Adapter\Out\IsbnProvider;

use App\Domain\Entity\DadosBibliograficos;
use App\Domain\Port\Out\IsbnProvider;
use App\Domain\ValueObject\Dimensoes;
use App\Domain\ValueObject\ISBN;
use App\Domain\ValueObject\Preco;

final class BrasilApiClient implements IsbnProvider
{
    public function __construct(private readonly HttpClient $http) {}

    public function nome(): string
    {
        return 'brasilapi';
    }

    public function buscarPorIsbn(ISBN $isbn): ?DadosBibliograficos
    {
        $url = 'https://brasilapi.com.br/api/isbn/v1/' . urlencode($isbn->isbn13());
        $res = $this->http->get($url);

        if ($res['status'] !== 200 || !is_array($res['json'])) {
            return null;
        }
        $j = $res['json'];

        $dim = new Dimensoes();
        if (isset($j['dimensions']) && is_array($j['dimensions'])) {
            $unidade = strtolower($j['dimensions']['unit'] ?? 'cm');
            $fator   = $unidade === 'mm' ? 0.1 : 1.0;
            $dim = new Dimensoes(
                alturaCm:    isset($j['dimensions']['height']) ? (float) $j['dimensions']['height'] * $fator : null,
                larguraCm:   isset($j['dimensions']['width'])  ? (float) $j['dimensions']['width']  * $fator : null,
                espessuraCm: isset($j['dimensions']['depth'])  ? (float) $j['dimensions']['depth']  * $fator : null,
            );
        }

        $preco = new Preco();
        if (isset($j['retail_price']) && is_array($j['retail_price'])) {
            $preco = new Preco(
                moeda: $j['retail_price']['currency'] ?? null,
                valor: isset($j['retail_price']['amount']) ? (float) $j['retail_price']['amount'] : null,
            );
        }

        $formato = $j['format'] ?? null;
        $formatoTraduzido = match (strtoupper((string) $formato)) {
            'PHYSICAL' => 'Físico',
            'DIGITAL'  => 'Digital',
            default    => $formato,
        };

        return new DadosBibliograficos(
            isbn13: $isbn->isbn13(),
            isbn10: $isbn->isbn10(),
            titulo: (string) ($j['title'] ?? ''),
            subtitulo: $j['subtitle'] ?? null,
            autores: (array) ($j['authors'] ?? []),
            editora: $j['publisher'] ?? null,
            anoPublicacao: isset($j['year']) ? (int) $j['year'] : null,
            dataPublicacao: isset($j['year']) ? (string) $j['year'] : null,
            idioma: 'pt-BR',
            paginas: isset($j['page_count']) ? (int) $j['page_count'] : null,
            sinopse: $j['synopsis'] ?? null,
            assuntos: (array) ($j['subjects'] ?? []),
            categorias: [],
            formato: $formatoTraduzido,
            dimensoes: $dim,
            peso: null,
            preco: $preco,
            localPublicacao: $j['location'] ?? null,
            capaUrl: $j['cover_url'] ?? null,
            capaThumbnail: $j['cover_url'] ?? null,
            linkPreview: null,
            avaliacaoMedia: null,
            qtdAvaliacoes: null,
            fonteApi: $this->nome(),
            providerOrigem: $j['provider'] ?? null,
            consultadoEm: date('c'),
            payloadBruto: $j,
        );
    }
}
