<?php
declare(strict_types=1);

/**
 * Migração idempotente da coluna textual legada `livros.hyb_categoria`
 * para a FK `livros.categoria_id`.
 *
 * Estratégia (decisão #11 — coluna D = nome de categoria):
 *   1) Lê todos os valores distintos de `hyb_categoria` em livros sem
 *      `categoria_id` setado.
 *   2) Para cada string, tenta casar (trim + lowercase) contra
 *      `categoria.descricao`.
 *   3) Match único → UPDATE livros SET categoria_id=? WHERE
 *      LOWER(TRIM(hyb_categoria)) = ? AND categoria_id IS NULL.
 *   4) Múltiplos matches ou nenhum → loga e PULA. Revisão manual depois.
 *
 * Idempotente: pode rodar várias vezes. Linhas já migradas (categoria_id
 * preenchido) são ignoradas no SELECT inicial.
 *
 * Executar:
 *   docker compose exec -T app php /var/www/html/scripts/migrar_categoria_legada.php
 */

$raiz = dirname(__DIR__);
require_once $raiz . '/vendor/autoload.php';

// .env — mesmo padrão de scripts/importar_categorias.php
if (class_exists(\Dotenv\Dotenv::class) && file_exists($raiz . '/.env')) {
    \Dotenv\Dotenv::createImmutable($raiz)->safeLoad();
}

// ---------------------------------------------------------------------
// PDO direto (script standalone — não passa pelo container)
// ---------------------------------------------------------------------
$host = $_ENV['DB_HOST'] ?? 'db';
$port = $_ENV['DB_PORT'] ?? '3306';
$db   = $_ENV['DB_NAME'] ?? 'isbn_app';
$user = $_ENV['DB_USER'] ?? 'isbn_user';
$pass = $_ENV['DB_PASS'] ?? 'changeme';

$pdo = new PDO(
    sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $host, $port, $db),
    $user,
    $pass,
    [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
    ]
);

// ---------------------------------------------------------------------
// 1) Coleta strings legadas pendentes de migração
// ---------------------------------------------------------------------
$sqlStrings = <<<SQL
SELECT DISTINCT hyb_categoria
FROM livros
WHERE hyb_categoria IS NOT NULL
  AND TRIM(hyb_categoria) <> ''
  AND categoria_id IS NULL
SQL;

$strings = $pdo->query($sqlStrings)->fetchAll(PDO::FETCH_COLUMN);

if (count($strings) === 0) {
    fwrite(STDOUT, "Nenhuma string legada pendente. Nada a migrar.\n");
    fwrite(STDOUT, "Resumo: 0 migrados | 0 ambiguos | 0 nao encontrados\n");
    exit(0);
}

fwrite(STDOUT, sprintf("Encontradas %d strings legadas distintas.\n\n", count($strings)));

// ---------------------------------------------------------------------
// 2) Carrega catálogo de categorias ativas, indexado por chave normalizada
// ---------------------------------------------------------------------
$stmtCat = $pdo->query(
    "SELECT id, descricao FROM categoria WHERE ativo = 1"
);

/** @var array<string, int[]> $indice  chave normalizada → lista de ids */
$indice = [];
foreach ($stmtCat->fetchAll() as $row) {
    $chave = mb_strtolower(trim((string) $row['descricao']));
    $indice[$chave] ??= [];
    $indice[$chave][] = (int) $row['id'];
}

// ---------------------------------------------------------------------
// 3) Processa cada string legada
// ---------------------------------------------------------------------
$updateStmt = $pdo->prepare(
    "UPDATE livros
        SET categoria_id = :cat_id
      WHERE LOWER(TRIM(hyb_categoria)) = :chave
        AND categoria_id IS NULL"
);

$migrados        = 0;
$ambiguos        = 0;
$naoEncontrados  = 0;

foreach ($strings as $original) {
    $chave = mb_strtolower(trim((string) $original));

    if ($chave === '') {
        continue;
    }

    if (!isset($indice[$chave])) {
        fwrite(STDOUT, sprintf("NAO ENCONTRADO: '%s'\n", $original));
        $naoEncontrados++;
        continue;
    }

    $ids = $indice[$chave];
    if (count($ids) > 1) {
        fwrite(STDOUT, sprintf(
            "AMBIGUO: '%s' (%d matches: ids=%s)\n",
            $original,
            count($ids),
            implode(',', $ids)
        ));
        $ambiguos++;
        continue;
    }

    $catId = $ids[0];
    $updateStmt->execute([
        ':cat_id' => $catId,
        ':chave'  => $chave,
    ]);
    $afetadas = $updateStmt->rowCount();

    fwrite(STDOUT, sprintf(
        "OK: '%s' -> categoria_id=%d (%d livros atualizados)\n",
        $original,
        $catId,
        $afetadas
    ));
    $migrados++;
}

// ---------------------------------------------------------------------
// 4) Resumo final
// ---------------------------------------------------------------------
fwrite(STDOUT, sprintf(
    "\nResumo: %d migrados | %d ambiguos | %d nao encontrados\n",
    $migrados,
    $ambiguos,
    $naoEncontrados
));
