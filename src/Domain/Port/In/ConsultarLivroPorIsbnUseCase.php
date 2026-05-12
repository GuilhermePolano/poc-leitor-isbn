<?php
declare(strict_types=1);

namespace App\Domain\Port\In;

interface ConsultarLivroPorIsbnUseCase
{
    /**
     * @return array{
     *   sucesso: bool,
     *   origem?: string,
     *   tempo_ms?: int,
     *   livro?: array,
     *   erro?: string,
     *   tentativas?: array
     * }
     */
    public function executar(string $isbn, bool $forcarReconsulta = false): array;
}
