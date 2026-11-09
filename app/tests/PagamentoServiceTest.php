<?php

declare(strict_types=1);

namespace App\Tests;

use App\Controllers\Services\PagamentoException;
use App\Controllers\Services\PagamentoService;
use App\Tests\Support\DatabaseTestCase;
use App\Tests\Support\Fixtures;

final class PagamentoServiceTest extends DatabaseTestCase
{
    use Fixtures;

    public function testPagamentoIntegralSemJuros(): void
    {
        $bancoId = $this->createBank(100000);
        $contaId = $this->createConta(10000);
        $parcelaId = $this->createParcela($contaId, 10000, '2026-01-10');

        $resultado = PagamentoService::pagarParcela($parcelaId, $bancoId, '2026-01-15', null, 0, null);

        $this->assertSame($parcelaId, $resultado['id']);
        $this->assertSame($contaId, $resultado['conta_id']);
        $this->assertSame(10000, $resultado['principal_em_centavos']);
        $this->assertSame(0, $resultado['juros_pagos_em_centavos']);
        $this->assertSame(10000, $resultado['total_pago_em_centavos']);
        $this->assertSame(10000, $resultado['valor_pago_em_centavos']);
        $this->assertSame(0, $resultado['valor_restante_em_centavos']);
        $this->assertSame(0, $resultado['juros_em_centavos']);
        $this->assertSame('2026-01-15', $resultado['data_pagamento']);
        $this->assertSame($bancoId, $resultado['banco_id']);
        $this->assertTrue($resultado['paid']);
        $this->assertFalse($resultado['parcial']);

        $parcela = $this->fetchParcela($parcelaId);
        $this->assertSame(1, $parcela['paid']);
        $this->assertSame(10000, $parcela['valor_pago_em_centavos']);
        $this->assertSame(0, $parcela['juros_em_centavos']);
        $this->assertSame('2026-01-15', $parcela['data_pagamento']);
        $this->assertSame($bancoId, $parcela['banco_id']);

        $ledger = $this->fetchLedger($parcelaId);
        $this->assertCount(1, $ledger);
        $this->assertSame(10000, $ledger[0]['valor_em_centavos']);
        $this->assertSame(0, $ledger[0]['juros_em_centavos']);
        $this->assertSame($bancoId, $ledger[0]['banco_id']);
        $this->assertSame('2026-01-15', $ledger[0]['data_pagamento']);
        $this->assertNull($ledger[0]['usuario_id']);

        $this->assertSame(90000, $this->fetchBanco($bancoId)['saldo_em_centavos']);
    }

    public function testPagamentoParcialMantemParcelaAberta(): void
    {
        $bancoId = $this->createBank(100000);
        $contaId = $this->createConta(10000);
        $parcelaId = $this->createParcela($contaId, 10000);

        $resultado = PagamentoService::pagarParcela($parcelaId, $bancoId, '2026-01-15', 4000, 0, null);

        $this->assertSame(4000, $resultado['principal_em_centavos']);
        $this->assertSame(4000, $resultado['total_pago_em_centavos']);
        $this->assertSame(4000, $resultado['valor_pago_em_centavos']);
        $this->assertSame(6000, $resultado['valor_restante_em_centavos']);
        $this->assertFalse($resultado['paid']);
        $this->assertTrue($resultado['parcial']);

        $parcela = $this->fetchParcela($parcelaId);
        $this->assertSame(0, $parcela['paid']);
        $this->assertSame(4000, $parcela['valor_pago_em_centavos']);
        $this->assertSame('2026-01-15', $parcela['data_pagamento']);
        $this->assertSame($bancoId, $parcela['banco_id']);

        $this->assertCount(1, $this->fetchLedger($parcelaId));
        $this->assertSame(96000, $this->fetchBanco($bancoId)['saldo_em_centavos']);
    }

    public function testPagamentosParciaisAcumulamAteQuitar(): void
    {
        $bancoId = $this->createBank(100000);
        $contaId = $this->createConta(10000);
        $parcelaId = $this->createParcela($contaId, 10000);

        $primeiro = PagamentoService::pagarParcela($parcelaId, $bancoId, '2026-01-05', 3000, 0, null);
        $this->assertSame(7000, $primeiro['valor_restante_em_centavos']);
        $this->assertFalse($primeiro['paid']);

        $segundo = PagamentoService::pagarParcela($parcelaId, $bancoId, '2026-01-10', 4000, 0, null);
        $this->assertSame(7000, $segundo['valor_pago_em_centavos']);
        $this->assertSame(3000, $segundo['valor_restante_em_centavos']);
        $this->assertFalse($segundo['paid']);

        $terceiro = PagamentoService::pagarParcela($parcelaId, $bancoId, '2026-01-15', null, 0, null);
        $this->assertSame(3000, $terceiro['principal_em_centavos']);
        $this->assertSame(3000, $terceiro['total_pago_em_centavos']);
        $this->assertSame(10000, $terceiro['valor_pago_em_centavos']);
        $this->assertSame(0, $terceiro['valor_restante_em_centavos']);
        $this->assertTrue($terceiro['paid']);
        $this->assertFalse($terceiro['parcial']);

        $parcela = $this->fetchParcela($parcelaId);
        $this->assertSame(1, $parcela['paid']);
        $this->assertSame(10000, $parcela['valor_pago_em_centavos']);
        $this->assertSame('2026-01-15', $parcela['data_pagamento']);

        $ledger = $this->fetchLedger($parcelaId);
        $this->assertCount(3, $ledger);
        $this->assertSame(10000, array_sum(array_column($ledger, 'valor_em_centavos')));
        $this->assertSame(['2026-01-05', '2026-01-10', '2026-01-15'], array_column($ledger, 'data_pagamento'));

        $this->assertSame(90000, $this->fetchBanco($bancoId)['saldo_em_centavos']);
    }

    public function testJurosEntramNoCaixaENoRestante(): void
    {
        $bancoId = $this->createBank(100000);
        $contaId = $this->createConta(10000);
        $parcelaId = $this->createParcela($contaId, 10000);

        $primeiro = PagamentoService::pagarParcela($parcelaId, $bancoId, '2026-01-15', 4000, 500, null);

        $this->assertSame(4000, $primeiro['principal_em_centavos']);
        $this->assertSame(500, $primeiro['juros_pagos_em_centavos']);
        $this->assertSame(4500, $primeiro['total_pago_em_centavos']);
        $this->assertSame(4500, $primeiro['valor_pago_em_centavos']);
        $this->assertSame(500, $primeiro['juros_em_centavos']);
        $this->assertSame(6000, $primeiro['valor_restante_em_centavos']);
        $this->assertFalse($primeiro['paid']);

        $ledger = $this->fetchLedger($parcelaId);
        $this->assertCount(1, $ledger);
        $this->assertSame(4000, $ledger[0]['valor_em_centavos']);
        $this->assertSame(500, $ledger[0]['juros_em_centavos']);

        $this->assertSame(95500, $this->fetchBanco($bancoId)['saldo_em_centavos']);

        $segundo = PagamentoService::pagarParcela($parcelaId, $bancoId, '2026-01-20', null, 0, null);

        $this->assertSame(6000, $segundo['principal_em_centavos']);
        $this->assertSame(6000, $segundo['total_pago_em_centavos']);
        $this->assertSame(10500, $segundo['valor_pago_em_centavos']);
        $this->assertSame(500, $segundo['juros_em_centavos']);
        $this->assertSame(0, $segundo['valor_restante_em_centavos']);
        $this->assertTrue($segundo['paid']);

        $this->assertSame(89500, $this->fetchBanco($bancoId)['saldo_em_centavos']);
    }

    public function testJurosNoPagamentoIntegral(): void
    {
        $bancoId = $this->createBank(100000);
        $contaId = $this->createConta(10000);
        $parcelaId = $this->createParcela($contaId, 10000);

        $resultado = PagamentoService::pagarParcela($parcelaId, $bancoId, '2026-01-15', null, 500, null);

        $this->assertSame(10000, $resultado['principal_em_centavos']);
        $this->assertSame(500, $resultado['juros_pagos_em_centavos']);
        $this->assertSame(10500, $resultado['total_pago_em_centavos']);
        $this->assertSame(10500, $resultado['valor_pago_em_centavos']);
        $this->assertSame(0, $resultado['valor_restante_em_centavos']);
        $this->assertTrue($resultado['paid']);

        $ledger = $this->fetchLedger($parcelaId);
        $this->assertCount(1, $ledger);
        $this->assertSame(10000, $ledger[0]['valor_em_centavos']);
        $this->assertSame(500, $ledger[0]['juros_em_centavos']);

        $this->assertSame(89500, $this->fetchBanco($bancoId)['saldo_em_centavos']);
    }

    public function testPrincipalZeroComJurosFalha(): void
    {
        $bancoId = $this->createBank(100000);
        $contaId = $this->createConta(10000);
        $parcelaId = $this->createParcela($contaId, 10000);
        $parcelaAntes = $this->fetchParcela($parcelaId);

        $this->assertPagamentoFalha(
            'O valor do pagamento deve ser maior que zero.',
            $parcelaId,
            $bancoId,
            0,
            500
        );

        $this->assertSame($parcelaAntes, $this->fetchParcela($parcelaId));
        $this->assertSame([], $this->fetchLedger($parcelaId));
        $this->assertSame(100000, $this->fetchBanco($bancoId)['saldo_em_centavos']);
    }

    public function testPrincipalMaiorQueRestanteFalha(): void
    {
        $bancoId = $this->createBank(100000);
        $contaId = $this->createConta(10000);
        $parcelaId = $this->createParcela($contaId, 10000);
        $parcelaAntes = $this->fetchParcela($parcelaId);

        $this->assertPagamentoFalha(
            'O valor do pagamento não pode ser maior que o valor restante da parcela.',
            $parcelaId,
            $bancoId,
            10001,
            0
        );

        $this->assertSame($parcelaAntes, $this->fetchParcela($parcelaId));
        $this->assertSame([], $this->fetchLedger($parcelaId));
        $this->assertSame(100000, $this->fetchBanco($bancoId)['saldo_em_centavos']);
    }

    public function testParcelaJaPagaFalha(): void
    {
        $bancoId = $this->createBank(100000);
        $contaId = $this->createConta(10000);
        $parcelaId = $this->createParcela($contaId, 10000, '2026-01-10', [
            'valor_pago_em_centavos' => 10000,
            'data_pagamento' => '2026-01-05',
            'banco_id' => $bancoId,
            'paid' => 1,
        ]);
        $parcelaAntes = $this->fetchParcela($parcelaId);

        $this->assertPagamentoFalha('Parcela já foi paga.', $parcelaId, $bancoId, null, 0);

        $this->assertSame($parcelaAntes, $this->fetchParcela($parcelaId));
        $this->assertSame([], $this->fetchLedger($parcelaId));
        $this->assertSame(100000, $this->fetchBanco($bancoId)['saldo_em_centavos']);
    }

    public function testParcelaInativaFalha(): void
    {
        $bancoId = $this->createBank(100000);
        $contaId = $this->createConta(10000);
        $parcelaId = $this->createParcela($contaId, 10000, '2026-01-10', ['enabled' => 0]);
        $parcelaAntes = $this->fetchParcela($parcelaId);

        $this->assertPagamentoFalha(
            'Não é possível pagar uma parcela inativa.',
            $parcelaId,
            $bancoId,
            4000,
            0
        );

        $this->assertSame($parcelaAntes, $this->fetchParcela($parcelaId));
        $this->assertSame([], $this->fetchLedger($parcelaId));
        $this->assertSame(100000, $this->fetchBanco($bancoId)['saldo_em_centavos']);
    }

    public function testParcelaInexistenteFalha(): void
    {
        $bancoId = $this->createBank(100000);

        $this->assertPagamentoFalha('Parcela não encontrada.', 999999, $bancoId, 1000, 0);

        $this->assertSame(100000, $this->fetchBanco($bancoId)['saldo_em_centavos']);
    }

    public function testBancoInexistenteFalha(): void
    {
        $contaId = $this->createConta(10000);
        $parcelaId = $this->createParcela($contaId, 10000);
        $parcelaAntes = $this->fetchParcela($parcelaId);

        $this->assertPagamentoFalha('Conta bancária não encontrada.', $parcelaId, 999999, 4000, 0);

        $this->assertSame($parcelaAntes, $this->fetchParcela($parcelaId));
        $this->assertSame([], $this->fetchLedger($parcelaId));
    }

    public function testBancoInativoFalha(): void
    {
        $bancoId = $this->createBank(100000, 0);
        $contaId = $this->createConta(10000);
        $parcelaId = $this->createParcela($contaId, 10000);
        $parcelaAntes = $this->fetchParcela($parcelaId);

        $this->assertPagamentoFalha(
            'Não é possível pagar usando uma conta bancária inativa.',
            $parcelaId,
            $bancoId,
            4000,
            0
        );

        $this->assertSame($parcelaAntes, $this->fetchParcela($parcelaId));
        $this->assertSame([], $this->fetchLedger($parcelaId));
        $this->assertSame(100000, $this->fetchBanco($bancoId)['saldo_em_centavos']);
    }

    public function testDataInvalidaFalha(): void
    {
        $bancoId = $this->createBank(100000);
        $contaId = $this->createConta(10000);
        $parcelaId = $this->createParcela($contaId, 10000);
        $parcelaAntes = $this->fetchParcela($parcelaId);

        $this->assertPagamentoFalha('Data inválida.', $parcelaId, $bancoId, 4000, 0, '2026-02-30');

        $this->assertSame($parcelaAntes, $this->fetchParcela($parcelaId));
        $this->assertSame([], $this->fetchLedger($parcelaId));
        $this->assertSame(100000, $this->fetchBanco($bancoId)['saldo_em_centavos']);
    }

    public function testJurosNegativoFalha(): void
    {
        $bancoId = $this->createBank(100000);
        $contaId = $this->createConta(10000);
        $parcelaId = $this->createParcela($contaId, 10000);
        $parcelaAntes = $this->fetchParcela($parcelaId);

        $this->assertPagamentoFalha('O valor do juros é inválido.', $parcelaId, $bancoId, 4000, -1);

        $this->assertSame($parcelaAntes, $this->fetchParcela($parcelaId));
        $this->assertSame([], $this->fetchLedger($parcelaId));
    }

    public function testJurosAcimaDoLimiteFalha(): void
    {
        $bancoId = $this->createBank(100000);
        $contaId = $this->createConta(10000);
        $parcelaId = $this->createParcela($contaId, 10000);
        $parcelaAntes = $this->fetchParcela($parcelaId);

        $this->assertPagamentoFalha('O valor do juros é inválido.', $parcelaId, $bancoId, 4000, 100000001);

        $this->assertSame($parcelaAntes, $this->fetchParcela($parcelaId));
        $this->assertSame([], $this->fetchLedger($parcelaId));
    }

    private function assertPagamentoFalha(
        string $mensagem,
        int $parcelaId,
        int $bancoId,
        int|null $principal,
        int $juros,
        string $data = '2026-01-15'
    ): void {
        try {
            PagamentoService::pagarParcela($parcelaId, $bancoId, $data, $principal, $juros, null);
            $this->fail('Era esperada uma PagamentoException.');
        } catch (PagamentoException $e) {
            $this->assertSame($mensagem, $e->getMessage());
        }
    }
}
