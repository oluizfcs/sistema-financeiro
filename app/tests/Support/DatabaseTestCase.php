<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Models\Database;
use PDO;
use PHPUnit\Framework\TestCase;
use RuntimeException;

abstract class DatabaseTestCase extends TestCase
{
    protected PDO $pdo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pdo = Database::getConnection();
        $this->assertTestDatabase();
        $this->truncateAllTables();

        $_SESSION = [];
    }

    protected function assertTestDatabase(): void
    {
        $current = (string) $this->pdo->query('SELECT DATABASE()')->fetchColumn();
        $configured = (string) ($_ENV['DB_NAME'] ?? '');

        if ($current === 'sistema_financeiro' || $current !== $configured || preg_match('/_test(?:_|$)/', $current) !== 1) {
            throw new RuntimeException("Refusing to use unsafe test database: {$current}");
        }
    }

    protected function truncateAllTables(): void
    {
        $this->pdo->exec('SET FOREIGN_KEY_CHECKS=0');

        try {
            $tables = $this->pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);

            foreach ($tables as $table) {
                $quoted = '`' . str_replace('`', '``', (string) $table) . '`';
                $this->pdo->exec("TRUNCATE TABLE {$quoted}");
            }
        } finally {
            $this->pdo->exec('SET FOREIGN_KEY_CHECKS=1');
        }
    }
}
