<?php
declare(strict_types=1);

/**
 * GET /api/exportar.php
 *   ?ids=1,2,3                  — exporta apenas IDs específicos
 *   ?apenas_nao_exportados=1    — exporta só livros com exportado_em NULL
 *   (sem parâmetros)            — exporta tudo
 *
 * Faz download direto do XLSX gerado.
 */

$container = require __DIR__ . '/../bootstrap/container.php';

try {
    $filtros = [];
    if (!empty($_GET['ids'])) {
        $ids = array_filter(array_map('intval', explode(',', (string) $_GET['ids'])));
        if (count($ids) > 0) {
            $filtros['ids'] = $ids;
        }
    }
    if (!empty($_GET['apenas_nao_exportados'])) {
        $filtros['apenas_nao_exportados'] = true;
    }

    $caminho = $container->casoUsoExportar()->executar($filtros);

    if (!is_file($caminho)) {
        throw new RuntimeException('Arquivo gerado não encontrado: ' . $caminho);
    }

    $nome = basename($caminho);
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $nome . '"');
    header('Content-Length: ' . filesize($caminho));
    header('Cache-Control: no-store, no-cache');
    readfile($caminho);
    exit;
} catch (Throwable $e) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'sucesso' => false,
        'erro'    => $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
}
