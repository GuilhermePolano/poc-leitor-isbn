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
        public ?int $qtdBaixas = null,
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

    /**
     * Formata exportado_em no padrão BR 'dd/MM/yyyy HH:mm'.
     * Centralizado na entidade para garantir mesma apresentação em todas as
     * camadas (lista, modal, atalho pós-cadastro).
     */
    public function exportadoEmBr(): ?string
    {
        if ($this->exportadoEm === null || $this->exportadoEm === '') {
            return null;
        }
        $dt = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $this->exportadoEm);
        if ($dt === false) {
            $ts = strtotime($this->exportadoEm);
            return $ts === false ? null : date('d/m/Y H:i', $ts);
        }
        return $dt->format('d/m/Y H:i');
    }

    /**
     * Formata exportado_em em ISO 8601 para consumo programático.
     */
    public function exportadoEmIso(): ?string
    {
        if ($this->exportadoEm === null || $this->exportadoEm === '') {
            return null;
        }
        $dt = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $this->exportadoEm);
        if ($dt === false) {
            $ts = strtotime($this->exportadoEm);
            return $ts === false ? null : date(DATE_ATOM, $ts);
        }
        return $dt->format(DATE_ATOM);
    }

    /**
     * Quantidade default para popular o modal de baixa (decisão #5):
     * exemplares físicos editáveis, fallback 1.
     */
    public function quantidadeDefault(): int
    {
        $qtd = $this->hyb->estoqueInicialQtd;
        return ($qtd !== null && $qtd > 0) ? $qtd : 1;
    }

    public function toArray(): array
    {
        return [
            'id'              => $this->id,
            'livro_api'       => $this->dadosApi->toArray(),
            'hyb'             => $this->hyb->toArray(),
            'consultado_em'   => $this->consultadoEm,
            'atualizado_em'   => $this->atualizadoEm,
            'exportado_em'    => $this->exportadoEmIso(),
            'exportado_em_br' => $this->exportadoEmBr(),
            'qtd_baixas'      => (int) ($this->qtdBaixas ?? 0),
            'quantidade'      => $this->quantidadeDefault(),
        ];
    }
}
