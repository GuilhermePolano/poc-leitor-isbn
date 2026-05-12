<?php
declare(strict_types=1);

namespace App\Domain\Port\Out;

interface Logger
{
    public function info(string $mensagem, array $contexto = []): void;
    public function warn(string $mensagem, array $contexto = []): void;
    public function error(string $mensagem, array $contexto = []): void;
}
