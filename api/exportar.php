<?php
declare(strict_types=1);

/**
 * Endpoint de exportação HYB — gera XLSX e dispara o download.
 *
 * Modos suportados:
 *
 *  GET (retro-compat):
 *    ?ids=1,2,3                  — exporta apenas IDs específicos
 *    ?apenas_nao_exportados=1    — exporta só livros com exportado_em NULL
 *    (sem parâmetros)            — exporta tudo
 *    origem implícita = "lista"; nenhum override de quantidade.
 *
 *  POST application/json:
 *    {
 *      "ids":         [1, 2, 3],
 *      "quantidades": { "1": 3, "2": 5 },           // override por livro (opcional)
 *      "origem":      "lista" | "atalho_bipagem"    // opcional, default = "lista"
 *    }
 *
 * Em ambos os modos, em caso de sucesso o XLSX é devolvido como
 * Content-Disposition: attachment.
 */

$container = require __DIR__ . '/../bootstrap/container.php';

try {
    $filtros = [];
    $metodo  = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

    if ($metodo === 'POST') {
        $bruto = file_get_contents('php://input') ?: '';
        $body  = $bruto !== '' ? json_decode($bruto, true) : [];
        if (!is_array($body)) {
            throw new RuntimeException('Corpo JSON inválido.');
        }

        if (!empty($body['ids']) && is_array($body['ids'])) {
            $ids = array_values(array_filter(array_map('intval', $body['ids']), fn ($v) => $v > 0));
            if (count($ids) > 0) {
                $filtros['ids'] = $ids;
            }
        }
        if (!empty($body['quantidades']) && is_array($body['quantidades'])) {
            $quantidades = [];
            foreach ($body['quantidades'] as $k => $v) {
                $livroId = (int) $k;
                $qtd     = (int) $v;
                if ($livroId > 0 && $qtd > 0) {
                    $quantidades[$livroId] = $qtd;
                }
            }
            if ($quantidades !== []) {
                $filtros['quantidades'] = $quantidades;
            }
        }
        if (!empty($body['origem']) && is_string($body['origem'])) {
            $filtros['origem'] = $body['origem'];
        }
    } else {
        // GET — formato legado
        if (!empty($_GET['ids'])) {
            $ids = array_values(array_filter(array_map('intval', explode(',', (string) $_GET['ids'])), fn ($v) => $v > 0));
            if (count($ids) > 0) {
                $filtros['ids'] = $ids;
            }
        }
        if (!empty($_GET['apenas_nao_exportados'])) {
            $filtros['apenas_nao_exportados'] = true;
        }
        // origem implícita = "lista" (default no service)
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
