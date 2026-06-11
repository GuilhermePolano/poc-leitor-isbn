<?php
declare(strict_types=1);

namespace App\Domain\Port\Out;

use App\Domain\Entity\Categoria;

interface CategoriaRepository
{
    /** @return Categoria[] */
    public function listarTodas(): array;

    public function buscarPorId(int $id): ?Categoria;

    public function buscarPorCodigo(int $codigo): ?Categoria;
}
