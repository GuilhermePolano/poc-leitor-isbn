<?php
declare(strict_types=1);

namespace App\Domain\Port\In;

interface SalvarLivroUseCase
{
    /**
     * Persiste (ou atualiza) o livro com os dados da API e os campos HYB.
     * @return array{ sucesso: bool, id: int, isbn_13: string, criado: bool }
     */
    public function executar(array $livroApi, array $hyb): array;
}
