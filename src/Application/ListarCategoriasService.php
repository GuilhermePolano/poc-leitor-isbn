<?php
declare(strict_types=1);

namespace App\Application;

use App\Domain\Entity\Categoria;
use App\Domain\Port\Out\CategoriaRepository;

/**
 * Caso de uso: obter todas as categorias ativas para popular o dropdown
 * de seleção exibido no formulário de cadastro de livro (decisão #4).
 */
final class ListarCategoriasService
{
    public function __construct(private readonly CategoriaRepository $repo) {}

    /** @return Categoria[] */
    public function executar(): array
    {
        return $this->repo->listarTodas();
    }
}
