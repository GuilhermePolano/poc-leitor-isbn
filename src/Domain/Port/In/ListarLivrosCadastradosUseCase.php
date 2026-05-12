<?php
declare(strict_types=1);

namespace App\Domain\Port\In;

interface ListarLivrosCadastradosUseCase
{
    /**
     * @return array{
     *   total: int,
     *   livros: array<int, array>
     * }
     */
    public function executar(int $limite = 50, int $offset = 0, array $filtros = []): array;
}
