<?php
declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use PDO;
use PDOException;
use RuntimeException;

final class ConexaoPdo
{
    private static ?PDO $instancia = null;

    public static function obter(): PDO
    {
        if (self::$instancia !== null) {
            return self::$instancia;
        }

        $host = $_ENV['DB_HOST'] ?? 'localhost';
        $port = $_ENV['DB_PORT'] ?? '3306';
        $db   = $_ENV['DB_NAME'] ?? 'isbn_app';
        $user = $_ENV['DB_USER'] ?? 'root';
        $pass = $_ENV['DB_PASS'] ?? '';

        $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $host, $port, $db);

        try {
            self::$instancia = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
            ]);
        } catch (PDOException $e) {
            throw new RuntimeException('Falha na conexão com o banco: ' . $e->getMessage(), 0, $e);
        }

        return self::$instancia;
    }

    public static function resetar(): void
    {
        self::$instancia = null;
    }
}
