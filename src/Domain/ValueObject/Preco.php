<?php
declare(strict_types=1);

namespace App\Domain\ValueObject;

final class Preco
{
    public function __construct(
        public readonly ?string $moeda = null,
        public readonly ?float $valor = null,
    ) {}

    public function temValor(): bool
    {
        return $this->valor !== null;
    }

    public function toArray(): array
    {
        return [
            'moeda' => $this->moeda,
            'valor' => $this->valor,
        ];
    }

    public function formatado(): ?string
    {
        if (!$this->temValor()) {
            return null;
        }
        $simbolo = $this->moeda === 'BRL' ? 'R$' : ($this->moeda ?? '');
        return trim($simbolo . ' ' . number_format((float)$this->valor, 2, ',', '.'));
    }
}
