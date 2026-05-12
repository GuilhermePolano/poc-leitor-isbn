<?php
declare(strict_types=1);

namespace App\Domain\Port\Out;

use App\Domain\Entity\Livro;
use App\Domain\ValueObject\ISBN;

interface LivroRepository
{
    public function salvar(Livro $livro): Livro;

    public function buscarPorIsbn(ISBN $isbn): ?Livro;

    public function buscarPorId(int $id): ?Livro;

    /** @return Livro[] */
    public function listar(int $limite, int $offset, array $filtros = []): array;

    public function contar(array $filtros = []): int;

    /** @param int[] $ids @return Livro[] */
    public function buscarPorIds(array $ids): array;

    /** @return Livro[] */
    public function listarNaoExportados(): array;

    public function marcarComoExportados(array $ids, string $quando): void;
}
