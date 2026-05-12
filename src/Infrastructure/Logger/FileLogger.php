<?php
declare(strict_types=1);

namespace App\Infrastructure\Logger;

use App\Domain\Port\Out\Logger;

final class FileLogger implements Logger
{
    public function __construct(private readonly string $diretorio) {}

    public function info(string $mensagem, array $contexto = []): void
    {
        $this->escrever('INFO', $mensagem, $contexto);
    }

    public function warn(string $mensagem, array $contexto = []): void
    {
        $this->escrever('WARN', $mensagem, $contexto);
    }

    public function error(string $mensagem, array $contexto = []): void
    {
        $this->escrever('ERROR', $mensagem, $contexto);
    }

    private function escrever(string $nivel, string $mensagem, array $contexto): void
    {
        if (!is_dir($this->diretorio)) {
            @mkdir($this->diretorio, 0775, true);
        }
        $arquivo = $this->diretorio . DIRECTORY_SEPARATOR . 'app-' . date('Y-m-d') . '.log';
        $ctx = $contexto === [] ? '' : ' ' . json_encode($contexto, JSON_UNESCAPED_UNICODE);
        $linha = sprintf("[%s] [%s] %s%s%s", date('Y-m-d H:i:s'), $nivel, $mensagem, $ctx, PHP_EOL);
        @file_put_contents($arquivo, $linha, FILE_APPEND);
    }
}
