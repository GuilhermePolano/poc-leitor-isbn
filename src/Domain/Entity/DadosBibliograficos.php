<?php
declare(strict_types=1);

namespace App\Domain\Entity;

use App\Domain\ValueObject\Dimensoes;
use App\Domain\ValueObject\Preco;

/**
 * Modelo unificado retornado pelos adaptadores de IsbnProvider.
 * Cada cliente (BrasilAPI, Google Books, Open Library) converte sua
 * resposta crua para este formato antes de devolver ao domínio.
 */
final class DadosBibliograficos
{
    public function __construct(
        public readonly string $isbn13,
        public readonly ?string $isbn10 = null,
        public readonly string $titulo = '',
        public readonly ?string $subtitulo = null,
        public readonly array $autores = [],
        public readonly ?string $editora = null,
        public readonly ?int $anoPublicacao = null,
        public readonly ?string $dataPublicacao = null,
        public readonly ?string $idioma = null,
        public readonly ?int $paginas = null,
        public readonly ?string $sinopse = null,
        public readonly array $assuntos = [],
        public readonly array $categorias = [],
        public readonly ?string $formato = null,
        public readonly Dimensoes $dimensoes = new Dimensoes(),
        public readonly ?string $peso = null,
        public readonly Preco $preco = new Preco(),
        public readonly ?string $localPublicacao = null,
        public readonly ?string $capaUrl = null,
        public readonly ?string $capaThumbnail = null,
        public readonly ?string $linkPreview = null,
        public readonly ?float $avaliacaoMedia = null,
        public readonly ?int $qtdAvaliacoes = null,
        public readonly string $fonteApi = '',
        public readonly ?string $providerOrigem = null,
        public readonly ?string $consultadoEm = null,
        public readonly ?array $payloadBruto = null,
    ) {}

    public function toArray(): array
    {
        return [
            'isbn_10'          => $this->isbn10,
            'isbn_13'          => $this->isbn13,
            'titulo'           => $this->titulo,
            'subtitulo'        => $this->subtitulo,
            'autores'          => $this->autores,
            'editora'          => $this->editora,
            'ano_publicacao'   => $this->anoPublicacao,
            'data_publicacao'  => $this->dataPublicacao,
            'idioma'           => $this->idioma,
            'paginas'          => $this->paginas,
            'sinopse'          => $this->sinopse,
            'assuntos'         => $this->assuntos,
            'categorias'       => $this->categorias,
            'formato'          => $this->formato,
            'dimensoes'        => $this->dimensoes->toArray(),
            'peso'             => $this->peso,
            'preco'            => $this->preco->toArray(),
            'local_publicacao' => $this->localPublicacao,
            'capa_url'         => $this->capaUrl,
            'capa_thumbnail'   => $this->capaThumbnail,
            'link_preview'     => $this->linkPreview,
            'avaliacao_media'  => $this->avaliacaoMedia,
            'qtd_avaliacoes'   => $this->qtdAvaliacoes,
            'fonte_api'        => $this->fonteApi,
            'provider_origem'  => $this->providerOrigem,
            'consultado_em'    => $this->consultadoEm,
        ];
    }
}
