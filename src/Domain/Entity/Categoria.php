<?php
declare(strict_types=1);

namespace App\Domain\Entity;

/**
 * Entidade imutável que representa uma categoria do catálogo HYB.
 *
 * - $codigo: código numérico interno HYB (ex.: 96, 98, 100...)
 * - $indice: rótulo ordinal exibido ao operador (ex.: "9", "9.01", "9.38")
 * - $descricao: nome legível (vai para a coluna D do XLSX)
 * - $parentId: id da categoria pai (null para raiz)
 * - $ativo: flag para futura inativação sem perder histórico
 */
final class Categoria
{
    public function __construct(
        public readonly int $id,
        public readonly int $codigo,
        public readonly string $indice,
        public readonly string $descricao,
        public readonly ?int $parentId = null,
        public readonly bool $ativo = true,
    ) {}

    public function id(): int
    {
        return $this->id;
    }

    public function codigo(): int
    {
        return $this->codigo;
    }

    public function indice(): string
    {
        return $this->indice;
    }

    public function descricao(): string
    {
        return $this->descricao;
    }

    public function parentId(): ?int
    {
        return $this->parentId;
    }

    public function ativo(): bool
    {
        return $this->ativo;
    }

    public function toArray(): array
    {
        return [
            'id'        => $this->id,
            'codigo'    => $this->codigo,
            'indice'    => $this->indice,
            'descricao' => $this->descricao,
            'parent_id' => $this->parentId,
            'ativo'     => $this->ativo,
        ];
    }

    public static function fromArray(array $dados): self
    {
        return new self(
            id:        (int) $dados['id'],
            codigo:    (int) $dados['codigo'],
            indice:    (string) $dados['indice'],
            descricao: (string) $dados['descricao'],
            parentId:  isset($dados['parent_id']) && $dados['parent_id'] !== null
                ? (int) $dados['parent_id']
                : null,
            ativo:     isset($dados['ativo']) ? (bool) $dados['ativo'] : true,
        );
    }
}
