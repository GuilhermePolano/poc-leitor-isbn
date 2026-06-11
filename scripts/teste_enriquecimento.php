<?php
declare(strict_types=1);

/**
 * Teste rápido para validar enriquecimento E1.
 * Invoca o caso de uso de consulta diretamente (sem passar pelo HTTP)
 * e devolve um resumo dos novos campos.
 */

$container = require __DIR__ . '/../bootstrap/container.php';

$isbns = $argv[1] ?? '9788535914849,9780747532699';
$lista = explode(',', $isbns);

$useCase = $container->casoUsoConsultar();

$saida = [];
foreach ($lista as $isbn) {
    $isbn = trim($isbn);
    $resultado = $useCase->executar($isbn, true); // force=true para ignorar cache
    $l = $resultado['livro'] ?? [];
    $saida[$isbn] = [
        'sucesso'         => $resultado['sucesso'] ?? false,
        'origem'          => $resultado['origem'] ?? null,
        'titulo'          => $l['titulo'] ?? null,
        'idioma'          => $l['idioma'] ?? null,
        'fonte_api'       => $l['fonte_api'] ?? null,
        'contributors'    => $l['contributors'] ?? null,
        'maturity_rating' => $l['maturity_rating'] ?? null,
        'main_category'   => $l['main_category'] ?? null,
        'physical_format' => $l['physical_format'] ?? null,
        'edition_name'    => $l['edition_name'] ?? null,
        'series'          => $l['series'] ?? null,
    ];
}

echo json_encode($saida, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), PHP_EOL;
