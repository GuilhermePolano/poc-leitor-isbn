<?php
declare(strict_types=1);

/**
 * Importador idempotente das 39 categorias HYB.
 *
 * Lê "Docs_para_Consulta/Categorias (3).xlsx", aba "Dados",
 * linhas 2..40, colunas A (Código), B (Nome), C (Índice).
 *
 * Hierarquia:
 *   - Índice inteiro puro (ex.: "9")    → raiz, parent_id = NULL
 *   - Índice decimal     (ex.: "9.10")  → parent = floor(valor), i.e. "9"
 *
 * O Excel armazena "9.10" como float 9.1 (perde o zero à direita). Para
 * preservar o índice canônico de duas casas decimais, formatamos com
 * sprintf('%.2f', ...) quando o valor for float; inteiros puros vão como
 * string sem ponto.
 *
 * Idempotência via UNIQUE(codigo) + INSERT ... ON DUPLICATE KEY UPDATE.
 *
 * Executar:
 *   docker compose exec -T app php /var/www/html/scripts/importar_categorias.php
 */

use PhpOffice\PhpSpreadsheet\IOFactory;

$raiz = dirname(__DIR__);
require_once $raiz . '/vendor/autoload.php';

// .env (mesmo padrão de bootstrap/container.php)
if (class_exists(\Dotenv\Dotenv::class) && file_exists($raiz . '/.env')) {
    \Dotenv\Dotenv::createImmutable($raiz)->safeLoad();
}

// ---------------------------------------------------------------------
// PDO direto (não passa pelo container — script é standalone)
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
// Lê XLSX
// ---------------------------------------------------------------------
$arquivo = $raiz . '/Docs_para_Consulta/Categorias (3).xlsx';
if (!is_file($arquivo)) {
    fwrite(STDERR, "[ERRO] Arquivo não encontrado: $arquivo\n");
    exit(1);
}

$reader      = IOFactory::createReaderForFile($arquivo);
$reader->setReadDataOnly(true);
$spreadsheet = $reader->load($arquivo);
$aba         = $spreadsheet->getSheetByName('Dados');
if ($aba === null) {
    fwrite(STDERR, "[ERRO] Aba 'Dados' não encontrada no XLSX\n");
    exit(1);
}

/**
 * Converte o valor cru da coluna Índice em string canônica.
 *  - int   "9"        → "9"        (raiz)
 *  - float 9.1        → "9.10"     (preserva zero perdido pelo Excel)
 *  - float 9.01       → "9.01"
 *  - string "9.10"    → "9.10"     (já vem formatada)
 *  - string "9"       → "9"
 */
$formatarIndice = static function ($raw): string {
    if ($raw === null || $raw === '') {
        return '';
    }
    if (is_int($raw)) {
        return (string)$raw;
    }
    if (is_float($raw)) {
        // Inteiro armazenado como float (raro mas possível)
        if (floor($raw) === $raw && $raw < 100) {
            return (string)(int)$raw;
        }
        return sprintf('%.2f', $raw);
    }
    $s = trim((string)$raw);
    // Se string numérica sem ponto: inteiro
    if (ctype_digit($s)) {
        return $s;
    }
    // String com ponto: garantir duas casas (ex.: "9.1" → "9.10")
    if (is_numeric($s)) {
        return sprintf('%.2f', (float)$s);
    }
    return $s;
};

// ---------------------------------------------------------------------
// 1ª passada — coleta todas as linhas
// ---------------------------------------------------------------------
$linhas = [];
for ($r = 2; $r <= 40; $r++) {
    $codigoRaw = $aba->getCell("A{$r}")->getValue();
    $nome      = $aba->getCell("B{$r}")->getValue();
    $indiceRaw = $aba->getCell("C{$r}")->getValue();

    if ($codigoRaw === null || $codigoRaw === '' || $nome === null || $nome === '') {
        fwrite(STDOUT, "[skip] Linha {$r} vazia ou incompleta\n");
        continue;
    }

    $codigo = (int)$codigoRaw;
    $nome   = trim((string)$nome);
    $indice = $formatarIndice($indiceRaw);

    if ($indice === '') {
        fwrite(STDOUT, "[skip] Linha {$r} sem índice (código {$codigo})\n");
        continue;
    }

    $linhas[] = [
        'linha'   => $r,
        'codigo'  => $codigo,
        'nome'    => $nome,
        'indice'  => $indice,
    ];
}

if (count($linhas) === 0) {
    fwrite(STDERR, "[ERRO] Nenhuma linha válida encontrada\n");
    exit(1);
}

// ---------------------------------------------------------------------
// 2ª passada — insere raízes primeiro (índice inteiro), depois filhos
// ---------------------------------------------------------------------
$pdo->beginTransaction();

try {
    $upsert = $pdo->prepare(
        'INSERT INTO categoria (codigo, indice, descricao, parent_id)
         VALUES (:codigo, :indice, :descricao, :parent_id)
         ON DUPLICATE KEY UPDATE
             descricao = VALUES(descricao),
             indice    = VALUES(indice),
             parent_id = VALUES(parent_id)'
    );

    $buscarPorIndice = $pdo->prepare(
        'SELECT id FROM categoria WHERE indice = :indice LIMIT 1'
    );

    // Ordenar: raízes (sem ponto) antes dos filhos
    usort($linhas, static function (array $a, array $b): int {
        $aRaiz = strpos($a['indice'], '.') === false ? 0 : 1;
        $bRaiz = strpos($b['indice'], '.') === false ? 0 : 1;
        if ($aRaiz !== $bRaiz) {
            return $aRaiz <=> $bRaiz;
        }
        return strnatcmp($a['indice'], $b['indice']);
    });

    $inseridos = 0;
    $atualizados = 0;

    foreach ($linhas as $row) {
        $indice = $row['indice'];
        $ehRaiz = strpos($indice, '.') === false;

        $parentId = null;
        if (!$ehRaiz) {
            $raizIndice = (string)(int)floor((float)$indice);
            $buscarPorIndice->execute([':indice' => $raizIndice]);
            $found = $buscarPorIndice->fetch();
            if ($found === false) {
                fwrite(STDERR,
                    "[ERRO] Pai não encontrado para indice '{$indice}' "
                    . "(esperado raiz '{$raizIndice}')\n");
                throw new RuntimeException('Hierarquia inválida');
            }
            $parentId = (int)$found['id'];
        }

        $upsert->execute([
            ':codigo'    => $row['codigo'],
            ':indice'    => $indice,
            ':descricao' => $row['nome'],
            ':parent_id' => $parentId,
        ]);

        $marcador = $upsert->rowCount() === 1 ? 'INSERT' : 'UPDATE';
        if ($marcador === 'INSERT') {
            $inseridos++;
        } else {
            $atualizados++;
        }

        $parentTxt = $parentId === null ? '(raiz)' : "parent_id={$parentId}";
        fwrite(STDOUT, sprintf(
            "[%s] codigo=%-4d indice=%-6s %s — %s\n",
            $marcador,
            $row['codigo'],
            $indice,
            $parentTxt,
            $row['nome']
        ));
    }

    $pdo->commit();

    fwrite(STDOUT, sprintf(
        "\nResumo: %d processadas | %d inseridas | %d atualizadas/inalteradas\n",
        count($linhas),
        $inseridos,
        $atualizados
    ));
} catch (Throwable $e) {
    $pdo->rollBack();
    fwrite(STDERR, "[FALHA] " . $e->getMessage() . "\n");
    exit(1);
}
