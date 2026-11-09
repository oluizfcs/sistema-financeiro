<?php

declare(strict_types=1);

namespace App\Tests;

use App\Tests\Support\DatabaseTestCase;
use App\Tests\Support\Fixtures;
use PDO;

final class DatabaseSmokeTest extends DatabaseTestCase
{
    use Fixtures;

    public function testSchemaIsLoadedInAnIsolatedDatabase(): void
    {
        $database = (string) $this->pdo->query('SELECT DATABASE()')->fetchColumn();
        $tables = $this->pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);

        $this->assertStringEndsWith('_test', $database);
        $this->assertNotSame('sistema_financeiro', $database);

        foreach (['usuario', 'banco', 'conta', 'parcela', 'parcela_pagamento'] as $table) {
            $this->assertContains($table, $tables);
        }
    }

    public function testFixturesCreateAPaymentGraph(): void
    {
        $bankId = $this->createBank(50000);
        $contaId = $this->createConta(10000);
        $parcelaId = $this->createParcela($contaId, 10000);

        $parcela = $this->fetchParcela($parcelaId);
        $banco = $this->fetchBanco($bankId);

        $this->assertSame($contaId, $parcela['conta_id']);
        $this->assertSame(10000, $parcela['valor_em_centavos']);
        $this->assertSame(0, $parcela['valor_pago_em_centavos']);
        $this->assertSame(50000, $banco['saldo_em_centavos']);
        $this->assertSame([], $this->fetchLedger($parcelaId));
    }
}
