<?php

declare(strict_types=1);

namespace App\Tests;

use App\Controllers\Services\PagamentoService;
use App\Models\Conta;
use App\Tests\Support\DatabaseTestCase;
use App\Tests\Support\Fixtures;

final class ContaPagamentosTest extends DatabaseTestCase
{
    use Fixtures;

    public function testHasPaymentsVerdadeiroParaPagamentoParcial(): void
    {
        $contaId = $this->createConta(10000);
        $this->createParcela($contaId, 10000, '2026-01-10', [
            'valor_pago_em_centavos' => 4000,
            'data_pagamento' => '2026-01-05',
        ]);

        $this->assertTrue(Conta::hasPayments($contaId));
    }

    public function testHasPaymentsVerdadeiroParaParcelaPaga(): void
    {
        $contaId = $this->createConta(10000);
        $this->createParcela($contaId, 10000, '2026-01-10', [
            'valor_pago_em_centavos' => 10000,
            'data_pagamento' => '2026-01-05',
            'paid' => 1,
        ]);

        $this->assertTrue(Conta::hasPayments($contaId));
    }

    public function testHasPaymentsFalsoParaContaSemPagamentos(): void
    {
        $contaId = $this->createConta(10000);
        $this->createParcela($contaId, 10000);

        $this->assertFalse(Conta::hasPayments($contaId));
    }

    public function testFindInstallmentsRetornaCaches(): void
    {
        $bancoId = $this->createBank(100000);
        $contaId = $this->createConta(30000);

        $this->createParcela($contaId, 10000, '2026-01-10', [
            'juros_em_centavos' => 500,
            'valor_pago_em_centavos' => 4500,
            'data_pagamento' => '2026-01-05',
            'banco_id' => $bancoId,
        ]);
        $this->createParcela($contaId, 20000, '2026-01-20', [
            'valor_pago_em_centavos' => 20000,
            'data_pagamento' => '2026-01-15',
            'banco_id' => $bancoId,
            'paid' => 1,
        ]);

        $parcelas = Conta::findInstallments($contaId);

        $this->assertCount(2, $parcelas);
        $this->assertSame(1, $parcelas[0]->getNumero_parcela());
        $this->assertSame(4500, $parcelas[0]->getValor_pago_em_centavos());
        $this->assertSame(500, $parcelas[0]->getJuros_em_centavos());
        $this->assertSame(6000, $parcelas[0]->getValorRestante());
        $this->assertTrue($parcelas[0]->isParcial());

        $this->assertSame(2, $parcelas[1]->getNumero_parcela());
        $this->assertSame(20000, $parcelas[1]->getValor_pago_em_centavos());
        $this->assertSame(0, $parcelas[1]->getValorRestante());
        $this->assertFalse($parcelas[1]->isParcial());
    }

    public function testSyncValorFromParcelasMantemContaAbertaComParcial(): void
    {
        $usuarioId = $this->createUser();
        $this->setSessionUser($usuarioId);
        $contaId = $this->createConta(10000);
        $this->createParcela($contaId, 10000, '2026-01-10', [
            'valor_pago_em_centavos' => 4000,
            'data_pagamento' => '2026-01-05',
        ]);
        $this->createParcela($contaId, 20000, '2026-01-20');

        $conta = Conta::getById($contaId);
        $conta->syncValorFromParcelas();

        $persistida = $this->fetchConta($contaId);
        $this->assertSame(30000, $persistida['valor_em_centavos']);
        $this->assertSame(0, $persistida['paid']);
    }

    public function testSyncValorFromParcelasIncluiJuros(): void
    {
        $usuarioId = $this->createUser();
        $this->setSessionUser($usuarioId);
        $contaId = $this->createConta(10000);
        $this->createParcela($contaId, 10000, '2026-01-10', [
            'juros_em_centavos' => 500,
            'valor_pago_em_centavos' => 4500,
            'data_pagamento' => '2026-01-05',
        ]);
        $this->createParcela($contaId, 20000, '2026-01-20');

        $conta = Conta::getById($contaId);
        $conta->syncValorFromParcelas();

        $persistida = $this->fetchConta($contaId);
        $this->assertSame(30500, $persistida['valor_em_centavos']);
        $this->assertSame(0, $persistida['paid']);
    }

    public function testSyncValorFromParcelasMarcaContaPagaQuandoTudoQuitado(): void
    {
        $usuarioId = $this->createUser();
        $this->setSessionUser($usuarioId);
        $bancoId = $this->createBank(100000);
        $contaId = $this->createConta(30000);
        $parcelaA = $this->createParcela($contaId, 10000, '2026-01-10');
        $parcelaB = $this->createParcela($contaId, 20000, '2026-01-20');

        PagamentoService::pagarParcela($parcelaA, $bancoId, '2026-01-05', null, 0, $usuarioId);
        PagamentoService::pagarParcela($parcelaB, $bancoId, '2026-01-15', null, 0, $usuarioId);

        $conta = Conta::getById($contaId);
        $conta->syncValorFromParcelas();

        $persistida = $this->fetchConta($contaId);
        $this->assertSame(30000, $persistida['valor_em_centavos']);
        $this->assertSame(1, $persistida['paid']);
    }

    public function testHasUnpaidEHasPaidInstallments(): void
    {
        $contaParcial = $this->createConta(10000);
        $this->createParcela($contaParcial, 10000, '2026-01-10', [
            'valor_pago_em_centavos' => 4000,
            'data_pagamento' => '2026-01-05',
        ]);

        $contaPaga = $this->createConta(10000);
        $this->createParcela($contaPaga, 10000, '2026-01-10', [
            'valor_pago_em_centavos' => 10000,
            'data_pagamento' => '2026-01-05',
            'paid' => 1,
        ]);

        $this->assertTrue(Conta::hasUnpaidInstallments($contaParcial));
        $this->assertFalse(Conta::hasPaidInstallments($contaParcial));

        $this->assertFalse(Conta::hasUnpaidInstallments($contaPaga));
        $this->assertTrue(Conta::hasPaidInstallments($contaPaga));
    }
}
