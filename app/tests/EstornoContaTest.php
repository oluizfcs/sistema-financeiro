<?php

declare(strict_types=1);

namespace App\Tests;

use App\Controllers\Services\PagamentoService;
use App\Tests\Support\DatabaseTestCase;
use App\Tests\Support\Fixtures;

final class EstornoContaTest extends DatabaseTestCase
{
    use Fixtures;

    public function testEstornoRemoveLedgerEDevolveBancos(): void
    {
        $bancoA = $this->createBank(100000);
        $bancoB = $this->createBank(50000);
        $contaId = $this->createConta(30000);
        $parcelaA = $this->createParcela($contaId, 10000);
        $parcelaB = $this->createParcela($contaId, 20000);

        PagamentoService::pagarParcela($parcelaA, $bancoA, '2026-01-10', 4000, 500, null);
        PagamentoService::pagarParcela($parcelaB, $bancoB, '2026-01-12', null, 0, null);

        $this->assertSame(95500, $this->fetchBanco($bancoA)['saldo_em_centavos']);
        $this->assertSame(30000, $this->fetchBanco($bancoB)['saldo_em_centavos']);

        $resultado = PagamentoService::estornarConta($contaId, null);

        $this->assertSame(2, $resultado['estornoCount']);
        $this->assertSame(24500, $resultado['estornoTotal']);

        $this->assertSame(100000, $this->fetchBanco($bancoA)['saldo_em_centavos']);
        $this->assertSame(50000, $this->fetchBanco($bancoB)['saldo_em_centavos']);
        $this->assertSame([], $this->fetchLedger($parcelaA));
        $this->assertSame([], $this->fetchLedger($parcelaB));

        $this->assertParcelaEstornada($parcelaA);
        $this->assertParcelaEstornada($parcelaB);

        $segundo = PagamentoService::estornarConta($contaId, null);

        $this->assertSame(0, $segundo['estornoCount']);
        $this->assertSame(0, $segundo['estornoTotal']);
        $this->assertSame(100000, $this->fetchBanco($bancoA)['saldo_em_centavos']);
        $this->assertSame(50000, $this->fetchBanco($bancoB)['saldo_em_centavos']);
    }

    public function testEstornoDeContaPagaIntegralmente(): void
    {
        $bancoId = $this->createBank(100000);
        $contaId = $this->createConta(10000);
        $parcelaId = $this->createParcela($contaId, 10000);

        PagamentoService::pagarParcela($parcelaId, $bancoId, '2026-01-15', null, 0, null);

        $this->assertSame(90000, $this->fetchBanco($bancoId)['saldo_em_centavos']);

        $resultado = PagamentoService::estornarConta($contaId, null);

        $this->assertSame(1, $resultado['estornoCount']);
        $this->assertSame(10000, $resultado['estornoTotal']);
        $this->assertSame(100000, $this->fetchBanco($bancoId)['saldo_em_centavos']);
        $this->assertSame([], $this->fetchLedger($parcelaId));
        $this->assertParcelaEstornada($parcelaId);
    }

    public function testEstornoAgrupaPagamentosParciaisPorBanco(): void
    {
        $bancoA = $this->createBank(100000);
        $bancoB = $this->createBank(100000);
        $contaId = $this->createConta(10000);
        $parcelaId = $this->createParcela($contaId, 10000);

        PagamentoService::pagarParcela($parcelaId, $bancoA, '2026-01-05', 3000, 0, null);
        PagamentoService::pagarParcela($parcelaId, $bancoB, '2026-01-10', 2000, 500, null);

        $this->assertSame(97000, $this->fetchBanco($bancoA)['saldo_em_centavos']);
        $this->assertSame(97500, $this->fetchBanco($bancoB)['saldo_em_centavos']);

        $resultado = PagamentoService::estornarConta($contaId, null);

        $this->assertSame(1, $resultado['estornoCount']);
        $this->assertSame(5500, $resultado['estornoTotal']);
        $this->assertSame(100000, $this->fetchBanco($bancoA)['saldo_em_centavos']);
        $this->assertSame(100000, $this->fetchBanco($bancoB)['saldo_em_centavos']);
        $this->assertSame([], $this->fetchLedger($parcelaId));
        $this->assertParcelaEstornada($parcelaId);
    }

    public function testEstornoDeContaSemPagamentosNaoAlteraNada(): void
    {
        $bancoId = $this->createBank(100000);
        $contaId = $this->createConta(10000);
        $parcelaId = $this->createParcela($contaId, 10000);
        $parcelaAntes = $this->fetchParcela($parcelaId);

        $resultado = PagamentoService::estornarConta($contaId, null);

        $this->assertSame(0, $resultado['estornoCount']);
        $this->assertSame(0, $resultado['estornoTotal']);
        $this->assertSame($parcelaAntes, $this->fetchParcela($parcelaId));
        $this->assertSame([], $this->fetchLedger($parcelaId));
        $this->assertSame(100000, $this->fetchBanco($bancoId)['saldo_em_centavos']);
    }

    public function testEstornoDeContaSemParcelas(): void
    {
        $contaId = $this->createConta(0);

        $resultado = PagamentoService::estornarConta($contaId, null);

        $this->assertSame(0, $resultado['estornoCount']);
        $this->assertSame(0, $resultado['estornoTotal']);
    }

    public function testEstornoNaoAfetaOutraConta(): void
    {
        $bancoA = $this->createBank(100000);
        $bancoB = $this->createBank(100000);
        $contaA = $this->createConta(10000);
        $contaB = $this->createConta(20000);
        $parcelaA = $this->createParcela($contaA, 10000);
        $parcelaB = $this->createParcela($contaB, 20000);

        PagamentoService::pagarParcela($parcelaA, $bancoA, '2026-01-10', 4000, 0, null);
        PagamentoService::pagarParcela($parcelaB, $bancoB, '2026-01-10', 5000, 0, null);

        PagamentoService::estornarConta($contaA, null);

        $this->assertSame(100000, $this->fetchBanco($bancoA)['saldo_em_centavos']);
        $this->assertSame(95000, $this->fetchBanco($bancoB)['saldo_em_centavos']);
        $this->assertSame([], $this->fetchLedger($parcelaA));

        $this->assertCount(1, $this->fetchLedger($parcelaB));
        $parcelaBPersistida = $this->fetchParcela($parcelaB);
        $this->assertSame(0, $parcelaBPersistida['paid']);
        $this->assertSame(5000, $parcelaBPersistida['valor_pago_em_centavos']);
    }

    private function assertParcelaEstornada(int $parcelaId): void
    {
        $parcela = $this->fetchParcela($parcelaId);

        $this->assertSame(0, $parcela['valor_pago_em_centavos']);
        $this->assertSame(0, $parcela['juros_em_centavos']);
        $this->assertSame(0, $parcela['paid']);
        $this->assertNull($parcela['data_pagamento']);
        $this->assertNull($parcela['banco_id']);
    }
}
