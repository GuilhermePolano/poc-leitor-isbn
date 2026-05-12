<?php
declare(strict_types=1);

namespace App\Domain\Entity;

use App\Domain\ValueObject\Dimensoes;
use App\Domain\ValueObject\Preco;

/**
 * Agregado raiz que combina os dados bibliográficos vindos da API
 * com os campos complementares HYB preenchidos pelo operador.
 */
final class Livro
{
    public function __construct(
        public ?int $id,
        public DadosBibliograficos $dadosApi,
        public CamposHyb $hyb,
        public ?string $consultadoEm = null,
        public ?string $atualizadoEm = null,
        public ?string $exportadoEm = null,
    ) {}

    public function isbn13(): string
    {
        return $this->dadosApi->isbn13;
    }

    public function isbn10(): ?string
    {
        return $this->dadosApi->isbn10;
    }

    public function titulo(): string
    {
        return $this->dadosApi->titulo;
    }

    public function toArray(): array
    {
        return [
            'id'             => $this->id,
            'livro_api'      => $this->dadosApi->toArray(),
            'hyb'            => $this->hyb->toArray(),
            'consultado_em'  => $this->consultadoEm,
            'atualizado_em'  => $this->atualizadoEm,
            'exportado_em'   => $this->exportadoEm,
        ];
    }
}
