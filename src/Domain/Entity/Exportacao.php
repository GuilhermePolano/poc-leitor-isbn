<?php
declare(strict_types=1);

namespace App\Domain\Entity;

use DateTimeImmutable;

/**
 * Cabeçalho de uma baixa (batch) — representa um XLSX efetivamente gerado.
 *
 * Uma "baixa" é registrada somente após Writer::save() concluir com sucesso
 * (decisão #1). Isso evita registros órfãos quando a geração falha.
 *
 *  - $id              : PK auto-increment.
 *  - $arquivo         : nome do arquivo XLSX produzido (sem path).
 *  - $qtdRegistros    : total de linhas exportadas (igual a count($itens)).
 *  - $geradoEm        : instante do save() OK.
 *  - $usuario         : autor da baixa, opcional (placeholder até existir login).
 *  - $origem          : "lista" (fluxo padrão) ou "atalho_bipagem"
 *                       (atalho pós-cadastro — decisão #2).
 */
final class Exportacao
{
    public const ORIGEM_LISTA = 'lista';
    public const ORIGEM_ATALHO = 'atalho_bipagem';

    public function __construct(
        public readonly int $id,
        public readonly string $arquivo,
        public readonly int $qtdRegistros,
        public readonly DateTimeImmutable $geradoEm,
        public readonly ?string $usuario = null,
        public readonly string $origem = self::ORIGEM_LISTA,
    ) {}

    public function id(): int
    {
        return $this->id;
    }

    public function arquivo(): string
    {
        return $this->arquivo;
    }

    public function qtdRegistros(): int
    {
        return $this->qtdRegistros;
    }

    public function geradoEm(): DateTimeImmutable
    {
        return $this->geradoEm;
    }

    public function usuario(): ?string
    {
        return $this->usuario;
    }

    public function origem(): string
    {
        return $this->origem;
    }

    public function toArray(): array
    {
        return [
            'id'            => $this->id,
            'arquivo'       => $this->arquivo,
            'qtd_registros' => $this->qtdRegistros,
            'gerado_em'     => $this->geradoEm->format('Y-m-d H:i:s'),
            'usuario'       => $this->usuario,
            'origem'        => $this->origem,
        ];
    }
}
