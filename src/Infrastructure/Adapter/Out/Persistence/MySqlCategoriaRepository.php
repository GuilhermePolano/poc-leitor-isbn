<?php
declare(strict_types=1);

namespace App\Infrastructure\Adapter\Out\Persistence;

use App\Domain\Entity\Categoria;
use App\Domain\Port\Out\CategoriaRepository;
use PDO;

final class MySqlCategoriaRepository implements CategoriaRepository
{
    /**
     * Ordenação adotada (decisão #4 — dropdown linear ordenado por índice):
     *
     *   ORDER BY
     *     CAST(SUBSTRING_INDEX(indice, '.', 1) AS UNSIGNED) ASC,
     *     CASE WHEN LOCATE('.', indice) > 0
     *          THEN CAST(SUBSTRING_INDEX(indice, '.', -1) AS UNSIGNED)
     *          ELSE 0 END ASC
     *
     * Por quê: o índice é uma string tipo "9", "9.01", "9.10", "9.38". Ordenar
     * por string puro coloca "9.10" antes de "9.2" (lexicográfico). Convertendo
     * a parte antes e depois do "." para inteiro, obtemos a ordem natural
     * 9, 9.01, 9.02, ..., 9.10, ..., 9.20, ..., 9.38.
     *
     * O CASE garante que o nó raiz ("9", sem ponto) venha antes dos filhos,
     * tratando o subordem como 0 quando não há sufixo.
     *
     * Alternativa rejeitada: coluna auxiliar "ordem" int — exige manutenção
     * manual ao inserir novas categorias e não traz ganho real para 39 linhas.
     */
    private const ORDER_BY = <<<SQL
ORDER BY
    CAST(SUBSTRING_INDEX(indice, '.', 1) AS UNSIGNED) ASC,
    CASE WHEN LOCATE('.', indice) > 0
         THEN CAST(SUBSTRING_INDEX(indice, '.', -1) AS UNSIGNED)
         ELSE 0 END ASC
SQL;

    public function __construct(private readonly PDO $pdo) {}

    public function listarTodas(): array
    {
        $sql = "SELECT id, codigo, indice, descricao, parent_id, ativo
                FROM categoria
                WHERE ativo = 1 "
                . self::ORDER_BY;

        $stmt = $this->pdo->query($sql);
        $resultados = [];
        foreach ($stmt->fetchAll() as $row) {
            $resultados[] = $this->mapear($row);
        }
        return $resultados;
    }

    public function buscarPorId(int $id): ?Categoria
    {
        $stmt = $this->pdo->prepare(
            "SELECT id, codigo, indice, descricao, parent_id, ativo
             FROM categoria
             WHERE id = :id
             LIMIT 1"
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ? $this->mapear($row) : null;
    }

    public function buscarPorCodigo(int $codigo): ?Categoria
    {
        $stmt = $this->pdo->prepare(
            "SELECT id, codigo, indice, descricao, parent_id, ativo
             FROM categoria
             WHERE codigo = :codigo
             LIMIT 1"
        );
        $stmt->execute([':codigo' => $codigo]);
        $row = $stmt->fetch();
        return $row ? $this->mapear($row) : null;
    }

    private function mapear(array $row): Categoria
    {
        return new Categoria(
            id:        (int) $row['id'],
            codigo:    (int) $row['codigo'],
            indice:    (string) $row['indice'],
            descricao: (string) $row['descricao'],
            parentId:  $row['parent_id'] !== null ? (int) $row['parent_id'] : null,
            ativo:     (bool) $row['ativo'],
        );
    }
}
