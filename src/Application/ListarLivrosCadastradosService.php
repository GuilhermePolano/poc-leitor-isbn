<?php
declare(strict_types=1);

namespace App\Application;

use App\Domain\Port\In\ListarLivrosCadastradosUseCase;
use App\Domain\Port\Out\LivroRepository;

final class ListarLivrosCadastradosService implements ListarLivrosCadastradosUseCase
{
    public function __construct(private readonly LivroRepository $repo) {}

    public function executar(int $limite = 50, int $offset = 0, array $filtros = []): array
    {
        $livros = $this->repo->listar($limite, $offset, $filtros);
        $total  = $this->repo->contar($filtros);

        return [
            'total' => $total,
            'livros' => array_map(fn ($l) => $l->toArray(), $livros),
        ];
    }
}
