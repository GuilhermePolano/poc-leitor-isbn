<?php
declare(strict_types=1);

namespace App\Infrastructure\Adapter\Out\Persistence;

use App\Domain\Port\Out\HistoricoBipagemRepository;
use PDO;
use Throwable;

final class MySqlHistoricoBipagemRepository implements HistoricoBipagemRepository
{
    public function __construct(private readonly PDO $pdo) {}

    public function registrar(
        string $isbnLido,
        ?int $livroId,
        bool $sucesso,
        ?string $fonteApi,
        ?string $mensagemErro,
        ?string $ipOrigem
    ): void {
        try {
            $sql = "INSERT INTO historico_bipagens (isbn_lido, livro_id, sucesso, fonte_api, mensagem_erro, ip_origem, bipado_em)
                    VALUES (:isbn, :livro, :sucesso, :fonte, :erro, :ip, NOW())";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                ':isbn'    => $isbnLido,
                ':livro'   => $livroId,
                ':sucesso' => $sucesso ? 1 : 0,
                ':fonte'   => $fonteApi,
                ':erro'    => $mensagemErro !== null ? mb_substr($mensagemErro, 0, 500) : null,
                ':ip'      => $ipOrigem,
            ]);
        } catch (Throwable $e) {
            // Histórico falhando não deve quebrar a aplicação.
        }
    }
}
