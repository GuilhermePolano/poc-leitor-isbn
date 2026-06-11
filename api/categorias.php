<?php
declare(strict_types=1);

/**
 * GET /api/categorias.php
 *
 * Retorna a lista linear de categorias ativas, ordenada pelo índice (decisão #4).
 * Usada pelo dropdown de seleção de categoria no formulário de cadastro.
 *
 * Resposta: [{id, codigo, indice, descricao, parent_id}, ...]
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$container = require __DIR__ . '/../bootstrap/container.php';
$metodo = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

try {
    if ($metodo !== 'GET') {
        http_response_code(405);
        echo json_encode(['erro' => 'Método não suportado.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $categorias = $container->casoUsoListarCategorias()->executar();

    $payload = array_map(static fn ($c) => [
        'id'        => $c->id(),
        'codigo'    => $c->codigo(),
        'indice'    => $c->indice(),
        'descricao' => $c->descricao(),
        'parent_id' => $c->parentId(),
    ], $categorias);

    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'erro' => 'Erro interno: ' . $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
}
