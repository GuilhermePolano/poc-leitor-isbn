<?php
declare(strict_types=1);

namespace App\Infrastructure\Adapter\Out\IsbnProvider;

/**
 * Cliente HTTP minimalista compartilhado pelos adaptadores.
 *
 * Usa `file_get_contents` + stream_context (PHP nativo). Evita libcurl porque
 * algumas versões de Debian (libcurl 7.88 + OpenSSL 3.x) geram um JA3
 * fingerprint que o Cloudflare bloqueia ("TLS alert decode error"). Stream
 * contexts usam a stack TLS do PHP, que passa sem problema.
 *
 * Retorna ['status' => int, 'body' => ?string, 'json' => ?array].
 */
final class HttpClient
{
    public function __construct(private readonly int $timeoutSegundos = 5) {}

    /**
     * @param array<string,string> $headers
     * @return array{ status: int, body: ?string, json: ?array }
     */
    public function get(string $url, array $headers = []): array
    {
        $headersFormatados = [
            'Accept: application/json',
            'User-Agent: POC-CodigoBarras-PHP/1.0',
        ];
        foreach ($headers as $k => $v) {
            $headersFormatados[] = $k . ': ' . $v;
        }

        $ctx = stream_context_create([
            'http' => [
                'method'           => 'GET',
                'header'           => implode("\r\n", $headersFormatados) . "\r\n",
                'timeout'          => $this->timeoutSegundos,
                'follow_location'  => 1,
                'max_redirects'    => 3,
                'ignore_errors'    => true, // não levanta erro em 4xx/5xx — queremos o body
            ],
            'ssl' => [
                'verify_peer'      => false, // ambiente de POC; em produção habilitar
                'verify_peer_name' => false,
                'SNI_enabled'      => true,
            ],
        ]);

        $body = @file_get_contents($url, false, $ctx);
        $status = $this->extrairStatus($http_response_header ?? []);

        if ($body === false || $body === '') {
            return ['status' => $status, 'body' => null, 'json' => null];
        }

        $json = json_decode($body, true);
        return [
            'status' => $status,
            'body'   => $body,
            'json'   => is_array($json) ? $json : null,
        ];
    }

    private function extrairStatus(array $cabecalhos): int
    {
        // O último HTTP/1.1 NNN é o status final (depois de eventuais redirects).
        $status = 0;
        foreach ($cabecalhos as $h) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', $h, $m)) {
                $status = (int) $m[1];
            }
        }
        return $status;
    }
}
