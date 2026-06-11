<?php
declare(strict_types=1);

namespace App\Domain\Entity;

/**
 * Item de uma baixa (linha do XLSX gerado).
 *
 *  - $id             : PK auto-increment.
 *  - $exportacaoId   : FK para exportacoes_hyb.id (cabeçalho do batch).
 *  - $livroId        : FK para livros.id.
 *  - $quantidade     : snapshot do override de quantidade aplicado no
 *                      modal da baixa (decisão #12). NULL = sem override
 *                      (usou o valor padrão do livro / .env).
 */
final class ExportacaoItem
{
    public function __construct(
        public readonly int $id,
        public readonly int $exportacaoId,
        public readonly int $livroId,
        public readonly ?int $quantidade = null,
    ) {}

    public function id(): int
    {
        return $this->id;
    }

    public function exportacaoId(): int
    {
        return $this->exportacaoId;
    }

    public function livroId(): int
    {
        return $this->livroId;
    }

    public function quantidade(): ?int
    {
        return $this->quantidade;
    }

    public function toArray(): array
    {
        return [
            'id'            => $this->id,
            'exportacao_id' => $this->exportacaoId,
            'livro_id'      => $this->livroId,
            'quantidade'    => $this->quantidade,
        ];
    }
}
