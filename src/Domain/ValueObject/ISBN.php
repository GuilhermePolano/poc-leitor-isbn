<?php
declare(strict_types=1);

namespace App\Domain\ValueObject;

use InvalidArgumentException;

final class ISBN
{
    private string $valor;

    private function __construct(string $valorNormalizado)
    {
        $this->valor = $valorNormalizado;
    }

    public static function criar(string $bruto): self
    {
        $normalizado = self::normalizar($bruto);

        if (strlen($normalizado) === 10) {
            if (!self::validarIsbn10($normalizado)) {
                throw new InvalidArgumentException("ISBN-10 inválido: {$bruto}");
            }
            // Converte para ISBN-13 para padronizar o domínio
            return new self(self::converterIsbn10ParaIsbn13($normalizado));
        }

        if (strlen($normalizado) === 13) {
            if (!self::validarIsbn13($normalizado)) {
                throw new InvalidArgumentException("ISBN-13 inválido: {$bruto}");
            }
            return new self($normalizado);
        }

        throw new InvalidArgumentException("ISBN deve ter 10 ou 13 dígitos. Recebido: {$bruto}");
    }

    public function valor(): string
    {
        return $this->valor;
    }

    public function isbn13(): string
    {
        return $this->valor;
    }

    public function isbn10(): ?string
    {
        return self::converterIsbn13ParaIsbn10($this->valor);
    }

    public function __toString(): string
    {
        return $this->valor;
    }

    // ----- Normalização e validação -----------------------------------

    private static function normalizar(string $bruto): string
    {
        // Remove hífens, espaços e qualquer caractere que não seja dígito ou X
        $limpo = preg_replace('/[^0-9Xx]/', '', $bruto) ?? '';
        return strtoupper($limpo);
    }

    private static function validarIsbn13(string $isbn): bool
    {
        if (!preg_match('/^\d{13}$/', $isbn)) {
            return false;
        }
        $soma = 0;
        for ($i = 0; $i < 12; $i++) {
            $digito = (int) $isbn[$i];
            $soma += ($i % 2 === 0) ? $digito : $digito * 3;
        }
        $dv = (10 - ($soma % 10)) % 10;
        return $dv === (int) $isbn[12];
    }

    private static function validarIsbn10(string $isbn): bool
    {
        if (!preg_match('/^\d{9}[\dX]$/', $isbn)) {
            return false;
        }
        $soma = 0;
        for ($i = 0; $i < 9; $i++) {
            $soma += ((int) $isbn[$i]) * (10 - $i);
        }
        $ultimo = $isbn[9] === 'X' ? 10 : (int) $isbn[9];
        $soma += $ultimo;
        return $soma % 11 === 0;
    }

    private static function converterIsbn10ParaIsbn13(string $isbn10): string
    {
        $base = '978' . substr($isbn10, 0, 9);
        $soma = 0;
        for ($i = 0; $i < 12; $i++) {
            $digito = (int) $base[$i];
            $soma += ($i % 2 === 0) ? $digito : $digito * 3;
        }
        $dv = (10 - ($soma % 10)) % 10;
        return $base . $dv;
    }

    private static function converterIsbn13ParaIsbn10(string $isbn13): ?string
    {
        if (substr($isbn13, 0, 3) !== '978') {
            return null;
        }
        $base = substr($isbn13, 3, 9);
        $soma = 0;
        for ($i = 0; $i < 9; $i++) {
            $soma += ((int) $base[$i]) * (10 - $i);
        }
        $resto = $soma % 11;
        $dv = (11 - $resto) % 11;
        return $base . ($dv === 10 ? 'X' : (string) $dv);
    }
}
