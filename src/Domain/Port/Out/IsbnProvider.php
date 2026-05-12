<?php
declare(strict_types=1);

namespace App\Domain\Port\Out;

use App\Domain\Entity\DadosBibliograficos;
use App\Domain\ValueObject\ISBN;

interface IsbnProvider
{
    public function buscarPorIsbn(ISBN $isbn): ?DadosBibliograficos;

    public function nome(): string;
}
