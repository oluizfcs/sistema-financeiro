<?php

declare(strict_types=1);

namespace App\Tests;

use App\Controllers\Services\PagamentoService;
use App\Models\Database;
use App\Models\Parcela;
use App\Tests\Support\DatabaseTestCase;
use App\Tests\Support\Fixtures;

final class ParcelaRelatoriosTest extends DatabaseTestCase
{
    use Fixtures;

    public function testGetAllRetornaTotaisEParciais(): void
    {
        $bancoId = $this->createBank(100000);
        $contaId = $this->createConta(60000);

        $parcial = $this->createParcela($contaId, 10000, '2026-01-10', [
            'valor_pago_em_centavos' => 4000,
            'data_pagamento' => '2026-01-05',
            'banco_id' => $bancoId,
        ]);
        $paga = $this->createParcela($contaId, 20000, '2026-01-20', [
            'valor_pago_em_centavos' => 20000,
            'data_pagamento' => '2026-01-18',
            'banco_id' => $bancoId,
            'paid' => 1,
        ]);
        $aberta = $this->createParcela($contaId, 30000, '2026-01-30');

        $todas = $this->indexById(Parcela::getAll('todas'));

        $this->assertSame(10000, (int) $todas[$parcial]['valor_total_em_centavos']);
        $this->assertSame(6000, (int) $todas[$parcial]['valor_restante_em_centavos']);
        $this->assertSame(0, (int) $todas[$parcial]['paid']);

        $this->assertSame(20000, (int) $todas[$paga]['valor_total_em_centavos']);
        $this->assertSame(0, (int) $todas[$paga]['valor_restante_em_centavos']);
        $this->assertSame(1, (int) $todas[$paga]['paid']);

        $this->assertSame(30000, (int) $todas[$aberta]['valor_total_em_centavos']);
        $this->assertSame(30000, (int) $todas[$aberta]['valor_restante_em_centavos']);

        $this->assertSame(
            [$parcial, $aberta],
            array_map(static fn(array $row): int => (int) $row['id'], Parcela::getAll('a pagar'))
        );
        $this->assertSame(
            [$parcial, $paga],
            array_map(static fn(array $row): int => (int) $row['id'], Parcela::getAll('pagas'))
        );
    }

    public function testGetAllPagasIncluiParciaisNoPeriodoDoPagamento(): void
    {
        $bancoId = $this->createBank(100000);
        $contaId = $this->createConta(20000);

        $parcialJaneiro = $this->createParcela($contaId, 10000, '2026-01-10');
        $parcialFevereiro = $this->createParcela($contaId, 10000, '2026-02-10');

        PagamentoService::pagarParcela($parcialJaneiro, $bancoId, '2026-01-15', 4000, 0, null);
        PagamentoService::pagarParcela($parcialFevereiro, $bancoId, '2026-02-15', 3000, 0, null);

        $this->assertSame(
            [$parcialJaneiro],
            array_map(
                static fn(array $row): int => (int) $row['id'],
                Parcela::getAll('pagas', 'all', 'all', 'all', '2026-01-01', '2026-01-31')
            )
        );

        $this->assertSame(
            [$parcialFevereiro],
            array_map(
                static fn(array $row): int => (int) $row['id'],
                Parcela::getAll('pagas', 'all', 'all', 'all', '2026-02-01', '2026-02-28')
            )
        );
    }

    public function testGetAllPagasListaUmaLinhaPorPagamento(): void
    {
        $bancoId = $this->createBank(100000);
        $contaId = $this->createConta(10000);
        $parcelaId = $this->createParcela($contaId, 10000, '2026-11-10');

        PagamentoService::pagarParcela($parcelaId, $bancoId, '2026-11-01', 4000, 0, null);
        PagamentoService::pagarParcela($parcelaId, $bancoId, '2026-11-02', 3000, 100, null);
        PagamentoService::pagarParcela($parcelaId, $bancoId, '2026-12-01', null, 0, null);

        // O pagamento de 2026-11-01 precisa aparecer filtrando só 2026-11-01,
        // mesmo a parcela vencendo em 2026-11-10 e com data_pagamento posterior.
        $primeiraMetade = Parcela::getAll('pagas', 'all', 'all', 'all', '2026-11-01', '2026-11-01');

        $this->assertCount(1, $primeiraMetade);
        $this->assertSame('pagamento', $primeiraMetade[0]['origem']);
        $this->assertSame($parcelaId, (int) $primeiraMetade[0]['id']);
        $this->assertSame('2026-11-01', $primeiraMetade[0]['data_pagamento']);
        $this->assertSame(4000, (int) $primeiraMetade[0]['pagamento_total_em_centavos']);
        $this->assertSame(4000, (int) $primeiraMetade[0]['valor_total_em_centavos']);
        // Cache acumulado da parcela: principal + juros de todos os pagamentos.
        $this->assertSame(10100, (int) $primeiraMetade[0]['valor_pago_em_centavos']);
        $this->assertSame(0, (int) $primeiraMetade[0]['valor_restante_em_centavos']);
        $this->assertSame(1, (int) $primeiraMetade[0]['paid']);
        $this->assertSame($bancoId, (int) $primeiraMetade[0]['banco_id']);

        $novembro = Parcela::getAll('pagas', 'all', 'all', 'all', '2026-11-01', '2026-11-30');

        $this->assertCount(2, $novembro);
        $this->assertSame(['2026-11-01', '2026-11-02'], array_column($novembro, 'data_pagamento'));
        $this->assertSame([4000, 3100], array_map(static fn(array $row): int => (int) $row['pagamento_total_em_centavos'], $novembro));

        $dezembro = Parcela::getAll('pagas', 'all', 'all', 'all', '2026-12-01', '2026-12-31');

        $this->assertCount(1, $dezembro);
        // O restante é R$ 30,00 de principal; o juros já foi pago em novembro.
        $this->assertSame(3000, (int) $dezembro[0]['pagamento_total_em_centavos']);
    }

    public function testGetAllPagasParcelaSemLancamentosContinuaComoLinhaDeParcela(): void
    {
        $bancoId = $this->createBank(100000);
        $contaId = $this->createConta(20000);

        $legada = $this->createParcela($contaId, 10000, '2026-01-10', [
            'valor_pago_em_centavos' => 10700,
            'juros_em_centavos' => 700,
            'data_pagamento' => '2026-01-05',
            'banco_id' => $bancoId,
            'paid' => 1,
        ]);
        $comLancamentos = $this->createParcela($contaId, 10000, '2026-01-10');
        PagamentoService::pagarParcela($comLancamentos, $bancoId, '2026-01-15', null, 0, null);

        $rows = Parcela::getAll('pagas', 'all', 'all', 'all', '2026-01-01', '2026-01-31');

        $this->assertCount(2, $rows);
        $this->assertSame(['2026-01-05', '2026-01-15'], array_column($rows, 'data_pagamento'));

        $this->assertSame('parcela', $rows[0]['origem']);
        $this->assertSame($legada, (int) $rows[0]['id']);
        $this->assertNull($rows[0]['pagamento_id']);
        $this->assertSame(10700, (int) $rows[0]['pagamento_total_em_centavos']);

        $this->assertSame('pagamento', $rows[1]['origem']);
        $this->assertSame($comLancamentos, (int) $rows[1]['id']);
        $this->assertSame(10000, (int) $rows[1]['pagamento_total_em_centavos']);
    }

    public function testGetAllPagasContaComoRelatorioDePagos(): void
    {
        $bancoId = $this->createBank(100000);
        $contaId = $this->createConta(40000);

        $parcelaId = $this->createParcela($contaId, 10000, '2026-11-10');
        PagamentoService::pagarParcela($parcelaId, $bancoId, '2026-11-01', 4000, 0, null);
        PagamentoService::pagarParcela($parcelaId, $bancoId, '2026-11-25', null, 0, null);

        $quitadaDividida = $this->createParcela($contaId, 10000, '2026-11-20');
        PagamentoService::pagarParcela($quitadaDividida, $bancoId, '2026-11-05', 5000, 0, null);
        PagamentoService::pagarParcela($quitadaDividida, $bancoId, '2026-11-06', null, 0, null);

        $listagem = Parcela::getAll('pagas', 'all', 'all', 'all', '2026-11-01', '2026-11-30');
        $relatorio = Database::getReport('2026-11-01', '2026-11-30', 'all', 'all', 'all', 'paid');

        $this->assertCount(4, $listagem);
        $this->assertCount(4, $relatorio);
        $this->assertSame(20000, array_sum(array_map(static fn(array $row): int => (int) $row['pagamento_total_em_centavos'], $listagem)));
        $this->assertSame(20000, array_sum(array_map(static fn(array $row): int => (int) $row['valor_total_em_centavos'], $relatorio)));
    }

    public function testGetAllPagasRespeitaFiltrosDeDimensao(): void
    {
        $bancoId = $this->createBank(100000);
        $naturezaA = $this->createNatureza('Natureza A');
        $naturezaB = $this->createNatureza('Natureza B');
        $contaA = $this->createConta(10000, ['natureza_id' => $naturezaA]);
        $contaB = $this->createConta(10000, ['natureza_id' => $naturezaB]);

        $parcelaA = $this->createParcela($contaA, 10000, '2026-01-10');
        $parcelaB = $this->createParcela($contaB, 10000, '2026-01-10');
        PagamentoService::pagarParcela($parcelaA, $bancoId, '2026-01-15', null, 0, null);
        PagamentoService::pagarParcela($parcelaB, $bancoId, '2026-01-16', null, 0, null);

        $rows = Parcela::getAll('pagas', (string) $naturezaA, 'all', 'all', '2026-01-01', '2026-01-31');

        $this->assertCount(1, $rows);
        $this->assertSame($parcelaA, (int) $rows[0]['id']);
    }

    public function testGetDailyTotalTodasSeparaPagamentoEVencimento(): void
    {
        $bancoId = $this->createBank(100000);
        $contaId = $this->createConta(60000);

        $parcial = $this->createParcela($contaId, 10000, '2026-02-10');
        $aberta = $this->createParcela($contaId, 30000, '2026-02-10');
        $paga = $this->createParcela($contaId, 20000, '2026-02-20');

        PagamentoService::pagarParcela($parcial, $bancoId, '2026-02-05', 4000, 500, null);
        PagamentoService::pagarParcela($paga, $bancoId, '2026-02-15', null, 0, null);

        $todas = Parcela::getDailyTotal('2026-02-01', '2026-02-28', 'todas');

        $this->assertSame([
            ['date' => '2026-02-05', 'total' => 4500],
            ['date' => '2026-02-10', 'total' => 36000],
            ['date' => '2026-02-15', 'total' => 20000],
        ], $todas);

        $pagas = array_map(
            static fn(array $row): array => ['date' => $row['date'], 'total' => (int) $row['total']],
            Parcela::getDailyTotal('2026-02-01', '2026-02-28', 'pagas')
        );
        $this->assertSame([
            ['date' => '2026-02-05', 'total' => 4500],
            ['date' => '2026-02-15', 'total' => 20000],
        ], $pagas);

        $aPagar = array_map(
            static fn(array $row): array => ['date' => $row['date'], 'total' => (int) $row['total']],
            Parcela::getDailyTotal('2026-02-01', '2026-02-28', 'a_pagar')
        );
        $this->assertSame([
            ['date' => '2026-02-10', 'total' => 36000],
        ], $aPagar);

        $parcelaParcial = $this->fetchParcela($parcial);
        $this->assertSame(
            6000,
            $parcelaParcial['valor_em_centavos'] + $parcelaParcial['juros_em_centavos'] - $parcelaParcial['valor_pago_em_centavos']
        );
        $this->assertSame(30000, $this->fetchParcela($aberta)['valor_em_centavos']);
        $this->assertSame(1, $this->fetchParcela($paga)['paid']);
    }

    public function testGetDailyTotalFiltraNatureza(): void
    {
        $naturezaA = $this->createNatureza('Natureza A');
        $naturezaB = $this->createNatureza('Natureza B');
        $contaA = $this->createConta(10000, ['natureza_id' => $naturezaA]);
        $contaB = $this->createConta(20000, ['natureza_id' => $naturezaB]);

        $this->createParcela($contaA, 10000, '2026-03-10');
        $this->createParcela($contaB, 20000, '2026-03-10');

        $this->assertSame([
            ['date' => '2026-03-10', 'total' => 10000],
        ], Parcela::getDailyTotal('2026-03-01', '2026-03-31', 'todas', (string) $naturezaA));
    }

    public function testGetOverdueTotalsUsaRestante(): void
    {
        $vencido = date('Y-m-d', strtotime('-2 days'));
        $futuro = date('Y-m-d', strtotime('+2 days'));
        $contaId = $this->createConta(110000);

        $this->createParcela($contaId, 10000, $vencido, [
            'juros_em_centavos' => 500,
            'valor_pago_em_centavos' => 4500,
        ]);
        $this->createParcela($contaId, 30000, $vencido);
        $this->createParcela($contaId, 50000, $futuro);
        $this->createParcela($contaId, 20000, $vencido, [
            'valor_pago_em_centavos' => 20000,
            'data_pagamento' => date('Y-m-d', strtotime('-3 days')),
            'paid' => 1,
        ]);

        $atrasadas = Parcela::getOverdueTotals();

        $this->assertSame(2, (int) $atrasadas['quantidade']);
        $this->assertSame(36000, (int) $atrasadas['total']);
        $this->assertSame($vencido, $atrasadas['mais_antiga']);

        $noVencimento = Parcela::getDueInRangeTotals($vencido, $vencido);
        $this->assertSame(2, (int) $noVencimento['quantidade']);
        $this->assertSame(36000, (int) $noVencimento['total']);

        $futuras = Parcela::getDueInRangeTotals($futuro, $futuro);
        $this->assertSame(1, (int) $futuras['quantidade']);
        $this->assertSame(50000, (int) $futuras['total']);
    }

    public function testGetByBankRetornaLedgerComFiltroDeData(): void
    {
        $bancoA = $this->createBank(100000);
        $bancoB = $this->createBank(100000);
        $contaId = $this->createConta(10000);
        $parcelaId = $this->createParcela($contaId, 10000);

        PagamentoService::pagarParcela($parcelaId, $bancoA, '2026-01-05', 3000, 0, null);
        PagamentoService::pagarParcela($parcelaId, $bancoA, '2026-01-10', 2000, 500, null);
        PagamentoService::pagarParcela($parcelaId, $bancoB, '2026-01-12', 1000, 0, null);

        $doBancoA = Parcela::getByBank($bancoA, null);
        $this->assertCount(2, $doBancoA);
        $this->assertSame(3000, $doBancoA[0]->getValor_em_centavos());
        $this->assertSame(0, $doBancoA[0]->getJuros_em_centavos());
        $this->assertSame('2026-01-05', $doBancoA[0]->getData_pagamento());
        $this->assertSame(2000, $doBancoA[1]->getValor_em_centavos());
        $this->assertSame(500, $doBancoA[1]->getJuros_em_centavos());
        $this->assertSame($contaId, $doBancoA[1]->getConta_id());
        $this->assertSame(1, $doBancoA[1]->getNumero_parcela());
        $this->assertSame('Centro de teste', $doBancoA[1]->centro_de_custo);
        $this->assertSame('Fornecedor de teste', $doBancoA[1]->fornecedor);

        $doBancoADepoisDe08 = Parcela::getByBank($bancoA, '2026-01-08');
        $this->assertCount(1, $doBancoADepoisDe08);
        $this->assertSame(2000, $doBancoADepoisDe08[0]->getValor_em_centavos());

        $doBancoB = Parcela::getByBank($bancoB, null);
        $this->assertCount(1, $doBancoB);
        $this->assertSame(1000, $doBancoB[0]->getValor_em_centavos());
    }

    public function testGetPaidByBankBetweenRetornaLedgerComPeriodo(): void
    {
        $bancoId = $this->createBank(100000);
        $contaId = $this->createConta(30000);
        $parcelaA = $this->createParcela($contaId, 10000);
        $parcelaB = $this->createParcela($contaId, 20000);

        PagamentoService::pagarParcela($parcelaA, $bancoId, '2026-01-05', 3000, 0, null);
        PagamentoService::pagarParcela($parcelaA, $bancoId, '2026-01-10', 2000, 500, null);
        PagamentoService::pagarParcela($parcelaB, $bancoId, '2026-01-20', 4000, 0, null);

        $pagamentos = Parcela::getPaidByBankBetween($bancoId, '2026-01-01', '2026-01-15');

        $this->assertCount(2, $pagamentos);
        $this->assertSame(3000, $pagamentos[0]->getValor_em_centavos());
        $this->assertSame(0, $pagamentos[0]->getJuros_em_centavos());
        $this->assertSame(2000, $pagamentos[1]->getValor_em_centavos());
        $this->assertSame(500, $pagamentos[1]->getJuros_em_centavos());
        $this->assertSame('2026-01-10', $pagamentos[1]->getData_pagamento());
        $this->assertSame('Conta de teste', $pagamentos[1]->historico);
        $this->assertSame('Natureza de teste', $pagamentos[1]->natureza);
        $this->assertSame('Centro de teste', $pagamentos[1]->centro_de_custo);
        $this->assertSame(2, $pagamentos[1]->total_parcelas);
    }

    public function testGetPaidMonthsByBankIncluiPagamentosParciais(): void
    {
        $bancoA = $this->createBank(100000);
        $bancoB = $this->createBank(100000);
        $contaId = $this->createConta(10000);
        $parcelaId = $this->createParcela($contaId, 10000);

        PagamentoService::pagarParcela($parcelaId, $bancoA, '2026-01-05', 3000, 0, null);
        PagamentoService::pagarParcela($parcelaId, $bancoA, '2026-02-10', 2000, 0, null);
        PagamentoService::pagarParcela($parcelaId, $bancoB, '2026-03-15', 1000, 0, null);
        PagamentoService::pagarParcela($parcelaId, $bancoA, '2026-03-20', null, 0, null);

        $this->assertSame(['2026-03', '2026-02', '2026-01'], Parcela::getPaidMonthsByBank($bancoA));
        $this->assertSame(['2026-03'], Parcela::getPaidMonthsByBank($bancoB));
    }

    public function testDeleteByContaIdPreservaParcelaComPagamentoParcial(): void
    {
        $bancoId = $this->createBank(100000);
        $contaId = $this->createConta(30000);

        $parcial = $this->createParcela($contaId, 10000, '2026-01-10', [
            'valor_pago_em_centavos' => 4000,
            'data_pagamento' => '2026-01-05',
            'banco_id' => $bancoId,
        ]);
        $aberta = $this->createParcela($contaId, 10000, '2026-01-20');
        $paga = $this->createParcela($contaId, 10000, '2026-01-30', [
            'valor_pago_em_centavos' => 10000,
            'data_pagamento' => '2026-01-25',
            'banco_id' => $bancoId,
            'paid' => 1,
        ]);

        Parcela::deleteByContaId($contaId);

        $ids = array_map(static fn(Parcela $parcela): int => $parcela->getId(), Parcela::getByContaId($contaId));

        $this->assertContains($parcial, $ids);
        $this->assertNotContains($aberta, $ids);
        $this->assertContains($paga, $ids);
    }

    public function testGetAllRespeitaHabilitacaoDaContaEParcela(): void
    {
        $ativa = $this->createConta(10000);
        $inativa = $this->createConta(10000, ['enabled' => 0]);

        $aberta = $this->createParcela($ativa, 10000, '2026-01-10');
        $daContaInativa = $this->createParcela($inativa, 10000, '2026-01-11');
        $parcelaInativa = $this->createParcela($ativa, 10000, '2026-01-12', ['enabled' => 0]);
        $paga = $this->createParcela($ativa, 10000, '2026-01-13', [
            'valor_pago_em_centavos' => 10000,
            'data_pagamento' => '2026-01-13',
            'paid' => 1,
        ]);

        $ids = static fn(array $rows): array => array_map(static fn(array $row): int => (int) $row['id'], $rows);

        $this->assertSame([$aberta], $ids(Parcela::getAll('a pagar')));
        $this->assertSame([$paga], $ids(Parcela::getAll('pagas')));
        $this->assertSame([$aberta, $paga], $ids(Parcela::getAll('todas')));
        $this->assertSame([$daContaInativa], $ids(Parcela::getAll('inativadas')));
        $this->assertNotContains($parcelaInativa, $ids(Parcela::getAll('todas')));
    }

    public function testGetDailyTotalIgnoraCobrancasInativas(): void
    {
        $bancoId = $this->createBank(100000);
        $ativa = $this->createConta(10000);
        $inativa = $this->createConta(20000, ['enabled' => 0]);

        $this->createParcela($ativa, 10000, '2026-02-10');
        $parcelaContaInativa = $this->createParcela($inativa, 20000, '2026-02-10');
        $parcelaInativa = $this->createParcela($ativa, 30000, '2026-02-10', ['enabled' => 0]);

        $this->createPagamento($parcelaContaInativa, $bancoId, 20000, 0, '2026-02-05');
        $this->createPagamento($parcelaInativa, $bancoId, 30000, 0, '2026-02-05');

        $aPagar = Parcela::getDailyTotal('2026-02-01', '2026-02-28', 'a_pagar');
        $this->assertCount(1, $aPagar);
        $this->assertSame('2026-02-10', $aPagar[0]['date']);
        $this->assertSame(10000, (int) $aPagar[0]['total']);
        $this->assertSame([], Parcela::getDailyTotal('2026-02-01', '2026-02-28', 'pagas'));
        $this->assertSame(
            [['date' => '2026-02-10', 'total' => 10000]],
            Parcela::getDailyTotal('2026-02-01', '2026-02-28', 'todas')
        );
    }

    public function testConsultasDeBancoIgnoramCobrancasInativas(): void
    {
        $bancoId = $this->createBank(100000);
        $ativa = $this->createConta(10000);
        $inativa = $this->createConta(10000, ['enabled' => 0]);

        $parcelaAtiva = $this->createParcela($ativa, 10000, '2026-03-10');
        $parcelaContaInativa = $this->createParcela($inativa, 10000, '2026-03-10');
        $parcelaInativa = $this->createParcela($ativa, 10000, '2026-03-10', ['enabled' => 0]);

        $this->createPagamento($parcelaAtiva, $bancoId, 1000, 0, '2026-03-05');
        $this->createPagamento($parcelaContaInativa, $bancoId, 2000, 0, '2026-04-06');
        $this->createPagamento($parcelaInativa, $bancoId, 3000, 0, '2026-04-07');

        $extrato = Parcela::getByBank($bancoId, null);

        $this->assertCount(1, $extrato);
        $this->assertSame(1000, $extrato[0]->getValor_em_centavos());
        $this->assertCount(1, Parcela::getPaidByBankBetween($bancoId, '2026-01-01', '2026-12-31'));
        $this->assertSame(['2026-03'], Parcela::getPaidMonthsByBank($bancoId));
    }

    private function indexById(array $rows): array
    {
        $indexed = [];

        foreach ($rows as $row) {
            $indexed[(int) $row['id']] = $row;
        }

        return $indexed;
    }
}
