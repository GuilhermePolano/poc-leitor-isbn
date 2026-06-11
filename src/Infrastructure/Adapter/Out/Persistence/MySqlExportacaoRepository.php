<?php
declare(strict_types=1);

namespace App\Infrastructure\Adapter\Out\Persistence;

use App\Domain\Entity\Exportacao;
use App\Domain\Port\Out\ExportacaoRepository;
use DateTimeImmutable;
use PDO;
use RuntimeException;
use Throwable;

/**
 * Adapter MySQL/MariaDB para o histórico de baixas.
 *
 * Decisões:
 *  - #1 : registrar() é chamado APÓS Writer::save() OK (responsabilidade do
 *         caso de uso). Aqui só persistimos.
 *  - #8 : nunca apaga histórico — operações são insert/update; o ON DELETE
 *         CASCADE só dispara se o livro/batch for removido manualmente.
 *  - #12: snapshot de quantidade fica em exportacoes_hyb_itens.quantidade;
 *         livros.hyb_estoque_ini_qtd NÃO é alterado.
 */
final class MySqlExportacaoRepository implements ExportacaoRepository
{
    public function __construct(private readonly PDO $pdo) {}

    public function registrar(Exportacao $cabecalho, array $itens): int
    {
        if ($itens === []) {
            throw new RuntimeException('Não é possível registrar uma baixa sem itens.');
        }

        // Defesa: garante consistência entre qtdRegistros e a lista enviada.
        // (O caso de uso já deve ter calculado isso, mas blindamos aqui.)
        $qtd = count($itens);

        $this->pdo->beginTransaction();
        try {
            // 1) Cabeçalho ----------------------------------------------------
            $stmtCab = $this->pdo->prepare(
                "INSERT INTO exportacoes_hyb
                    (arquivo, qtd_registros, gerado_em, usuario, origem)
                 VALUES
                    (:arquivo, :qtd, :gerado_em, :usuario, :origem)"
            );
            $stmtCab->execute([
                ':arquivo'   => $cabecalho->arquivo(),
                ':qtd'       => $qtd,
                ':gerado_em' => $cabecalho->geradoEm()->format('Y-m-d H:i:s'),
                ':usuario'   => $cabecalho->usuario(),
                ':origem'    => $cabecalho->origem(),
            ]);

            $exportacaoId = (int) $this->pdo->lastInsertId();
            if ($exportacaoId <= 0) {
                throw new RuntimeException('Falha ao recuperar lastInsertId() de exportacoes_hyb.');
            }

            // 2) Itens em massa ----------------------------------------------
            // Constrói VALUES dinâmico — mais eficiente que executar N inserts.
            $placeholders = [];
            $params = [];
            $idx = 0;
            $livroIds = [];

            foreach ($itens as $item) {
                if (!isset($item['livro_id'])) {
                    throw new RuntimeException('Item sem livro_id em registrar().');
                }
                $livroId   = (int) $item['livro_id'];
                $quantidade = array_key_exists('quantidade', $item) && $item['quantidade'] !== null
                    ? (int) $item['quantidade']
                    : null;

                $placeholders[] = "(:e{$idx}, :l{$idx}, :q{$idx})";
                $params[":e{$idx}"] = $exportacaoId;
                $params[":l{$idx}"] = $livroId;
                $params[":q{$idx}"] = $quantidade;
                $livroIds[] = $livroId;
                $idx++;
            }

            $sqlItens = "INSERT INTO exportacoes_hyb_itens
                            (exportacao_id, livro_id, quantidade)
                         VALUES " . implode(', ', $placeholders);
            $stmtItens = $this->pdo->prepare($sqlItens);
            $stmtItens->execute($params);

            // 3) Marca livros como exportados (decisão #1 — só após save() OK)
            // Usamos IDs únicos para evitar UPDATE redundante se houver
            // duplicatas eventuais na lista de itens.
            $livroIdsUnicos = array_values(array_unique($livroIds));
            $inPlaceholders = [];
            $inParams = [];
            foreach ($livroIdsUnicos as $i => $lid) {
                $inPlaceholders[] = ":lid{$i}";
                $inParams[":lid{$i}"] = $lid;
            }
            $stmtUpd = $this->pdo->prepare(
                "UPDATE livros
                    SET exportado_em = NOW()
                  WHERE id IN (" . implode(',', $inPlaceholders) . ")"
            );
            $stmtUpd->execute($inParams);

            // 4) Commit ------------------------------------------------------
            $this->pdo->commit();
            return $exportacaoId;
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw new RuntimeException(
                'Falha ao registrar baixa: ' . $e->getMessage(),
                0,
                $e
            );
        }
    }

    public function buscarUltimaBaixaPorLivro(int $livroId): ?Exportacao
    {
        $sql = "SELECT e.id, e.arquivo, e.qtd_registros, e.gerado_em, e.usuario, e.origem
                  FROM exportacoes_hyb e
                  INNER JOIN exportacoes_hyb_itens i ON i.exportacao_id = e.id
                 WHERE i.livro_id = :livro
              ORDER BY e.gerado_em DESC, e.id DESC
                 LIMIT 1";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':livro' => $livroId]);
        $row = $stmt->fetch();
        return $row ? $this->mapear($row) : null;
    }

    public function contarBaixasPorLivro(int $livroId): int
    {
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) AS total
               FROM exportacoes_hyb_itens
              WHERE livro_id = :livro"
        );
        $stmt->execute([':livro' => $livroId]);
        $row = $stmt->fetch();
        return $row ? (int) $row['total'] : 0;
    }

    private function mapear(array $row): Exportacao
    {
        return new Exportacao(
            id:           (int) $row['id'],
            arquivo:      (string) $row['arquivo'],
            qtdRegistros: (int) $row['qtd_registros'],
            geradoEm:     new DateTimeImmutable((string) $row['gerado_em']),
            usuario:      $row['usuario'] !== null ? (string) $row['usuario'] : null,
            origem:       (string) $row['origem'],
        );
    }
}
