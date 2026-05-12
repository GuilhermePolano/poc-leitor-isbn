<?php
declare(strict_types=1);

namespace App\Domain\Port\Out;

interface ExportadorDeLivros
{
    /**
     * @param array $livros lista de App\Domain\Entity\Livro
     * @return string caminho absoluto do arquivo gerado
     */
    public function exportar(array $livros, string $caminhoDestino): string;
}
