<?php
declare(strict_types=1);

namespace App\Infrastructure\Adapter\Out\IsbnProvider;

use App\Domain\Entity\DadosBibliograficos;
use App\Domain\Port\Out\IsbnProvider;
use App\Domain\Service\NormalizadorDeLivro;
use App\Domain\ValueObject\Dimensoes;
use App\Domain\ValueObject\ISBN;
use App\Domain\ValueObject\Preco;

final class GoogleBooksClient implements IsbnProvider
{
    public function __construct(
        private readonly HttpClient $http,
        private readonly NormalizadorDeLivro $normalizador,
        private readonly ?string $apiKey = null,
    ) {}

    public function nome(): string
    {
        return 'google_books';
    }

    public function buscarPorIsbn(ISBN $isbn): ?DadosBibliograficos
    {
        $url = 'https://www.googleapis.com/books/v1/volumes?q=isbn:' . urlencode($isbn->isbn13());
        if ($this->apiKey !== null && $this->apiKey !== '') {
            $url .= '&key=' . urlencode($this->apiKey);
        }
        $res = $this->http->get($url);
        if ($res['status'] !== 200 || !is_array($res['json'])) {
            return null;
        }
        $j = $res['json'];
        if (!isset($j['items']) || !is_array($j['items']) || count($j['items']) === 0) {
            return null;
        }

        $info = $j['items'][0]['volumeInfo'] ?? [];
        if (!is_array($info)) {
            return null;
        }

        $isbn10 = null;
        if (isset($info['industryIdentifiers']) && is_array($info['industryIdentifiers'])) {
            foreach ($info['industryIdentifiers'] as $idObj) {
                if (($idObj['type'] ?? null) === 'ISBN_10') {
                    $isbn10 = $idObj['identifier'];
                }
            }
        }

        $dim = new Dimensoes();
        if (isset($info['dimensions']) && is_array($info['dimensions'])) {
            $dim = new Dimensoes(
                alturaCm:    isset($info['dimensions']['height'])    ? $this->parseCm($info['dimensions']['height'])    : null,
                larguraCm:   isset($info['dimensions']['width'])     ? $this->parseCm($info['dimensions']['width'])     : null,
                espessuraCm: isset($info['dimensions']['thickness']) ? $this->parseCm($info['dimensions']['thickness']) : null,
            );
        }

        $preco = new Preco();
        $saleInfo = $j['items'][0]['saleInfo'] ?? null;
        if (is_array($saleInfo) && isset($saleInfo['listPrice'])) {
            $preco = new Preco(
                moeda: $saleInfo['listPrice']['currencyCode'] ?? null,
                valor: isset($saleInfo['listPrice']['amount']) ? (float) $saleInfo['listPrice']['amount'] : null,
            );
        }

        $imageLinks = $info['imageLinks'] ?? [];
        $capa = $imageLinks['extraLarge']
            ?? $imageLinks['large']
            ?? $imageLinks['medium']
            ?? $imageLinks['small']
            ?? $imageLinks['thumbnail']
            ?? $imageLinks['smallThumbnail']
            ?? null;
        if (is_string($capa)) {
            $capa = str_replace('http://', 'https://', $capa);
        }
        $thumb = $imageLinks['thumbnail'] ?? $imageLinks['smallThumbnail'] ?? $capa;
        if (is_string($thumb)) {
            $thumb = str_replace('http://', 'https://', $thumb);
        }

        $ano = null;
        $data = $info['publishedDate'] ?? null;
        if (is_string($data) && preg_match('/^(\d{4})/', $data, $m)) {
            $ano = (int) $m[1];
        }

        return new DadosBibliograficos(
            isbn13: $isbn->isbn13(),
            isbn10: $isbn10 ?? $isbn->isbn10(),
            titulo: (string) ($info['title'] ?? ''),
            subtitulo: $info['subtitle'] ?? null,
            autores: (array) ($info['authors'] ?? []),
            editora: $info['publisher'] ?? null,
            anoPublicacao: $ano,
            dataPublicacao: $data,
            idioma: $info['language'] ?? null,
            paginas: isset($info['pageCount']) ? (int) $info['pageCount'] : null,
            sinopse: $info['description'] ?? null,
            assuntos: [],
            categorias: (array) ($info['categories'] ?? []),
            formato: $info['printType'] ?? null,
            dimensoes: $dim,
            peso: null,
            preco: $preco,
            localPublicacao: null,
            capaUrl: $capa,
            capaThumbnail: $thumb,
            linkPreview: $info['previewLink'] ?? ($info['infoLink'] ?? null),
            avaliacaoMedia: isset($info['averageRating']) ? (float) $info['averageRating'] : null,
            qtdAvaliacoes: isset($info['ratingsCount']) ? (int) $info['ratingsCount'] : null,
            fonteApi: $this->nome(),
            providerOrigem: 'google_books',
            consultadoEm: date('c'),
            payloadBruto: $j['items'][0] ?? null,
        );
    }

    private function parseCm($valor): ?float
    {
        if ($valor === null) return null;
        $v = strtolower((string) $valor);
        if (preg_match('/([\d.,]+)/', $v, $m)) {
            $n = (float) str_replace(',', '.', $m[1]);
            if (str_contains($v, 'mm')) {
                return $n * 0.1;
            }
            return $n;
        }
        return null;
    }
}
