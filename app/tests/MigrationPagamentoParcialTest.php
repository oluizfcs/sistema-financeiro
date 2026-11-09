<?php

declare(strict_types=1);

namespace App\Tests;

use App\Tests\Support\Fixtures;
use PDO;
use PHPUnit\Framework\TestCase;

final class MigrationPagamentoParcialTest extends TestCase
{
    use Fixtures;

    private PDO $pdo;
    private PDO $admin;
    private string $database;

    protected function setUp(): void
    {
        parent::setUp();

        $host = (string) $_ENV['HOST_NAME'];
        $user = (string) $_ENV['DB_USERNAME'];
        $password = (string) $_ENV['DB_PASSWORD'];
        $this->database = (string) $_ENV['DB_NAME'] . '_migration';

        if (preg_match('/_test(?:_|$)/', $this->database) !== 1 || $this->database === 'sistema_financeiro') {
            $this->fail("Banco de migration inseguro: {$this->database}");
        }

        $multiStatements = class_exists(\Pdo\Mysql::class)
            ? \Pdo\Mysql::ATTR_MULTI_STATEMENTS
            : PDO::MYSQL_ATTR_MULTI_STATEMENTS;

        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            $multiStatements => true,
        ];

        $this->admin = new PDO("mysql:host={$host};charset=utf8mb4", $user, $password, $options);
        $quoted = '`' . str_replace('`', '``', $this->database) . '`';

        $this->admin->exec("DROP DATABASE IF EXISTS {$quoted}");
        $this->admin->exec("CREATE DATABASE {$quoted} DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

        $schema = (string) file_get_contents(dirname(__DIR__) . '/config/database/schema.sql');
        $schema = preg_replace('/CREATE DATABASE.*?;/is', '', $schema, 1);
        $schema = preg_replace('/^\s*USE\s+[^;]+;\s*$/im', '', (string) $schema);

        $this->pdo = new PDO("mysql:host={$host};dbname={$this->database};charset=utf8mb4", $user, $password, $options);
        $this->pdo->exec((string) $schema);

        $this->pdo->exec('DROP TABLE parcela_pagamento');
        $this->pdo->exec('ALTER TABLE parcela DROP COLUMN valor_pago_em_centavos');
    }

    protected function tearDown(): void
    {
        if (isset($this->admin, $this->database)) {
            $quoted = '`' . str_replace('`', '``', $this->database) . '`';
            $this->admin->exec("DROP DATABASE IF EXISTS {$quoted}");
        }

        parent::tearDown();
    }

    public function testMigrationFazBackfillDasParcelasPagas(): void
    {
        $usuarioId = $this->createUser();
        $bancoId = $this->createBank(100000);
        $contaId = $this->createConta(60000);

        $parcelaPagaCompleta = $this->insertRow('parcela', [
            'numero_parcela' => 1,
            'valor_em_centavos' => 10000,
            'juros_em_centavos' => 500,
            'data_vencimento' => '2026-01-10',
            'data_pagamento' => '2026-01-05',
            'banco_id' => $bancoId,
            'conta_id' => $contaId,
            'paid' => 1,
            'enabled' => 1,
        ]);

        $parcelaPagaSemBancoOuData = $this->insertRow('parcela', [
            'numero_parcela' => 2,
            'valor_em_centavos' => 20000,
            'juros_em_centavos' => 0,
            'data_vencimento' => '2026-02-10',
            'data_pagamento' => null,
            'banco_id' => null,
            'conta_id' => $contaId,
            'paid' => 1,
            'enabled' => 1,
        ]);

        $parcelaAberta = $this->insertRow('parcela', [
            'numero_parcela' => 3,
            'valor_em_centavos' => 30000,
            'juros_em_centavos' => 0,
            'data_vencimento' => '2026-03-10',
            'data_pagamento' => null,
            'banco_id' => null,
            'conta_id' => $contaId,
            'paid' => 0,
            'enabled' => 1,
        ]);

        $migration = (string) file_get_contents(dirname(__DIR__) . '/config/database/migrations/20261031_pagamento_parcial.sql');
        $this->executeMigration($migration);

        $pagaCompleta = $this->fetchParcelaLegacy($parcelaPagaCompleta);
        $pagaSemDados = $this->fetchParcelaLegacy($parcelaPagaSemBancoOuData);
        $aberta = $this->fetchParcelaLegacy($parcelaAberta);

        $this->assertSame(10500, (int) $pagaCompleta['valor_pago_em_centavos']);
        $this->assertSame(1, (int) $pagaCompleta['paid']);
        $this->assertSame(20000, (int) $pagaSemDados['valor_pago_em_centavos']);
        $this->assertSame(1, (int) $pagaSemDados['paid']);
        $this->assertSame(0, (int) $aberta['valor_pago_em_centavos']);
        $this->assertSame(0, (int) $aberta['paid']);

        $ledger = $this->pdo->query('SELECT * FROM parcela_pagamento ORDER BY parcela_id')->fetchAll();

        $this->assertCount(1, $ledger);
        $this->assertSame($parcelaPagaCompleta, (int) $ledger[0]['parcela_id']);
        $this->assertSame($bancoId, (int) $ledger[0]['banco_id']);
        $this->assertSame(10000, (int) $ledger[0]['valor_em_centavos']);
        $this->assertSame(500, (int) $ledger[0]['juros_em_centavos']);
        $this->assertSame('2026-01-05', $ledger[0]['data_pagamento']);
        $this->assertNull($ledger[0]['usuario_id']);
    }

    private function executeMigration(string $sql): void
    {
        $sql = preg_replace('/^\s*--.*$/m', '', $sql);
        $statements = array_filter(array_map('trim', explode(';', (string) $sql)));

        foreach ($statements as $statement) {
            if ($statement === '' || stripos($statement, 'USE ') === 0) {
                continue;
            }

            if (stripos($statement, 'SELECT ') === 0) {
                $this->pdo->query($statement)->fetchAll();
                continue;
            }

            $this->pdo->exec($statement);
        }
    }

    private function fetchParcelaLegacy(int $id): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM parcela WHERE id = :id');
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: throw new \RuntimeException("Parcela {$id} não encontrada.");
    }
}
