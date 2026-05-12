<?php
declare(strict_types=1);

/**
 * GET  /api/livros.php           — lista paginada
 * POST /api/livros.php           — persiste livro consultado (com campos HYB)
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$container = require __DIR__ . '/../bootstrap/container.php';
$metodo = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

try {
    if ($metodo === 'GET') {
        $limite  = max(1, min(200, (int) ($_GET['limite'] ?? 50)));
        $offset  = max(0, (int) ($_GET['offset'] ?? 0));
        $filtros = [
            'busca'   => trim((string) ($_GET['busca'] ?? '')),
            'editora' => trim((string) ($_GET['editora'] ?? '')),
            'ano'     => trim((string) ($_GET['ano'] ?? '')),
            'idioma'  => trim((string) ($_GET['idioma'] ?? '')),
        ];
        $filtros = array_filter($filtros, fn ($v) => $v !== '' && $v !== null);

        $resultado = $container->casoUsoListar()->executar($limite, $offset, $filtros);
        $resultado['limite'] = $limite;
        $resultado['offset'] = $offset;
        echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($metodo === 'POST') {
        $raw  = file_get_contents('php://input') ?: '';
        $body = json_decode($raw, true);
        if (!is_array($body)) {
            http_response_code(400);
            echo json_encode(['sucesso' => false, 'erro' => 'JSON inválido.'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        if (!isset($body['livro_api']) || !is_array($body['livro_api'])) {
            http_response_code(400);
            echo json_encode(['sucesso' => false, 'erro' => 'Campo "livro_api" é obrigatório.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $hyb = is_array($body['hyb'] ?? null) ? $body['hyb'] : [];

        $resultado = $container->casoUsoSalvar()->executar($body['livro_api'], $hyb);
        http_response_code($resultado['criado'] ? 201 : 200);
        echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
        exit;
    }

    http_response_code(405);
    echo json_encode(['sucesso' => false, 'erro' => 'Método não suportado.'], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'sucesso' => false,
        'erro'    => 'Erro interno: ' . $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
}
