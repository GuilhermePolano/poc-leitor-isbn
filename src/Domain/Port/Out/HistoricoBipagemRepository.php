<?php
declare(strict_types=1);

namespace App\Domain\Port\Out;

interface HistoricoBipagemRepository
{
    public function registrar(
        string $isbnLido,
        ?int $livroId,
        bool $sucesso,
        ?string $fonteApi,
        ?string $mensagemErro,
        ?string $ipOrigem
    ): void;
}
