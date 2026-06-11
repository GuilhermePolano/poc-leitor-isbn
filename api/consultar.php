<?php
declare(strict_types=1);

/**
 * POST /api/consultar.php
 * Body JSON: { "isbn": "9788545702870", "force": false }
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

try {
    $raw  = file_get_contents('php://input') ?: '';
    $body = json_decode($raw, true);
    if (!is_array($body)) {
        $body = $_POST;
    }

    $isbn  = trim((string) ($body['isbn'] ?? $_GET['isbn'] ?? ''));
    $force = (bool) ($body['force'] ?? ($_GET['force'] ?? false));

    if ($isbn === '') {
        http_response_code(400);
        echo json_encode(['sucesso' => false, 'erro' => 'Parâmetro "isbn" é obrigatório.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $resultado = $container->casoUsoConsultar()->executar($isbn, $force);

    if ($resultado['sucesso']) {
        // Pré-preenche defaults HYB se o livro ainda não está cadastrado
        if (!($resultado['cadastrado'] ?? false)) {
            $defs   = $container->defaultsHyb();
            $livro  = $resultado['livro'];
            $hybAtual = $resultado['hyb'] ?? [];

            $resultado['hyb_defaults'] = [
                'bem_produto'       => '',
                'unidade'           => $defs['unidade'],
                'categoria'         => $defs['categoria'],
                'ncm'               => $defs['ncm'],
                'preco_venda'       => $livro['preco']['valor'] ?? '',
                'estoque_minimo'    => $defs['estoque_minimo'],
                'referencia'        => $livro['isbn_10'] ?? '',
                'patrimonio'        => $defs['patrimonio'],
                'depreciacao_pct'   => '',
                'tipo'              => $defs['tipo'],
                'estoque_ini_qtd'   => $defs['estoque_ini_qtd'],
                'estoque_ini_custo' => '',
                'descricao'         => $container->geradorDescricao()->gerarDeArray($livro),
            ];
        }
        echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
        exit;
    }

    http_response_code(404);
    echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'sucesso' => false,
        'erro'    => 'Erro interno: ' . $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
}

/**
 * @deprecated desde Fase 2.3 (M16). Mantida só como retro-compatibilidade caso
 * algum script externo importe este arquivo. A geração de descrição agora
 * acontece em App\Domain\Service\GeradorDescricaoLivro (via container).
 */
function gerarDescricaoSimples(array $livro): string
{
    $container = require __DIR__ . '/../bootstrap/container.php';
    return $container->geradorDescricao()->gerarDeArray($livro);
}
