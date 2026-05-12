<?php
declare(strict_types=1);

namespace App\Domain\ValueObject;

final class Dimensoes
{
    public function __construct(
        public readonly ?float $alturaCm = null,
        public readonly ?float $larguraCm = null,
        public readonly ?float $espessuraCm = null,
    ) {}

    public function temAlgumValor(): bool
    {
        return $this->alturaCm !== null
            || $this->larguraCm !== null
            || $this->espessuraCm !== null;
    }

    public function toArray(): array
    {
        return [
            'altura_cm'    => $this->alturaCm,
            'largura_cm'   => $this->larguraCm,
            'espessura_cm' => $this->espessuraCm,
        ];
    }

    public function formatado(): ?string
    {
        if (!$this->temAlgumValor()) {
            return null;
        }
        $partes = array_filter([
            $this->alturaCm    !== null ? number_format($this->alturaCm, 1, ',', '') : null,
            $this->larguraCm   !== null ? number_format($this->larguraCm, 1, ',', '') : null,
            $this->espessuraCm !== null ? number_format($this->espessuraCm, 1, ',', '') : null,
        ]);
        return implode(' × ', $partes) . ' cm';
    }
}
