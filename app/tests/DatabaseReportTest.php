<?php

declare(strict_types=1);

namespace App\Tests;

use App\Controllers\Services\PagamentoService;
use App\Models\Database;
use App\Tests\Support\DatabaseTestCase;
use App\Tests\Support\Fixtures;

final class DatabaseReportTest extends DatabaseTestCase
{
    use Fixtures;

    public function testGetReportPaidUsaPagamentosDoPeriodo(): void
    {
        $bancoId = $this->createBank(100000);
        $contaId = $this->createConta(30000);
        $parcelaA = $this->createParcela($contaId, 10000, '2026-01-10');
        $parcelaB = $this->createParcela($contaId, 20000, '2026-02-10');

        PagamentoService::pagarParcela($parcelaA, $bancoId, '2026-01-05', 4000, 500, null);
        PagamentoService::pagarParcela($parcelaA, $bancoId, '2026-01-20', 6000, 0, null);
        PagamentoService::pagarParcela($parcelaB, $bancoId, '2026-02-15', null, 0, null);

        $janeiro = $this->sortByPaymentDate(
            Database::getReport('2026-01-01', '2026-01-31', 'all', 'all', 'all', 'paid')
        );

        $this->assertCount(2, $janeiro);
        $this->assertSame('2026-01-05', $janeiro[0]['data_pagamento']);
        $this->assertSame(4500, (int) $janeiro[0]['valor_total_em_centavos']);
        $this->assertSame(4500, (int) $janeiro[0]['valor_pago_em_centavos']);
        $this->assertSame(0, (int) $janeiro[0]['valor_restante_em_centavos']);
        $this->assertSame(1, (int) $janeiro[0]['numero_parcela']);
        $this->assertSame(2, (int) $janeiro[0]['total_parcelas']);
        $this->assertSame(0, (int) $janeiro[0]['parcela_parcial']);

        $this->assertSame('2026-01-20', $janeiro[1]['data_pagamento']);
        $this->assertSame(6000, (int) $janeiro[1]['valor_total_em_centavos']);
        $this->assertSame(1, (int) $janeiro[1]['numero_parcela']);
        $this->assertSame(0, (int) $janeiro[1]['parcela_parcial']);

        $fevereiro = $this->sortByPaymentDate(
            Database::getReport('2026-02-01', '2026-02-28', 'all', 'all', 'all', 'paid')
        );

        $this->assertCount(1, $fevereiro);
        $this->assertSame('2026-02-15', $fevereiro[0]['data_pagamento']);
        $this->assertSame(20000, (int) $fevereiro[0]['valor_total_em_centavos']);
        $this->assertSame(2, (int) $fevereiro[0]['numero_parcela']);
        $this->assertSame(0, (int) $fevereiro[0]['parcela_parcial']);
    }

    public function testGetReportUnpaidMostraSaldoRestante(): void
    {
        $bancoId = $this->createBank(100000);
        $contaId = $this->createConta(60000);

        $parcial = $this->createParcela($contaId, 10000, '2026-01-10');
        $this->createParcela($contaId, 20000, '2026-02-10');
        $paga = $this->createParcela($contaId, 30000, '2026-01-20');

        PagamentoService::pagarParcela($parcial, $bancoId, '2026-01-05', 4000, 500, null);
        PagamentoService::pagarParcela($paga, $bancoId, '2026-01-15', null, 0, null);

        $janeiro = Database::getReport('2026-01-01', '2026-01-31', 'all', 'all', 'all', 'unpaid');

        $this->assertCount(1, $janeiro);
        $this->assertSame('2026-01-10', $janeiro[0]['data_vencimento']);
        $this->assertSame(6000, (int) $janeiro[0]['valor_total_em_centavos']);
        $this->assertSame(4500, (int) $janeiro[0]['valor_pago_em_centavos']);
        $this->assertSame(6000, (int) $janeiro[0]['valor_restante_em_centavos']);
        $this->assertSame(1, (int) $janeiro[0]['parcela_parcial']);

        $fevereiro = Database::getReport('2026-02-01', '2026-02-28', 'all', 'all', 'all', 'unpaid');

        $this->assertCount(1, $fevereiro);
        $this->assertSame('2026-02-10', $fevereiro[0]['data_vencimento']);
        $this->assertSame(20000, (int) $fevereiro[0]['valor_total_em_centavos']);
        $this->assertSame(20000, (int) $fevereiro[0]['valor_restante_em_centavos']);
        $this->assertSame(2, (int) $fevereiro[0]['numero_parcela']);
        $this->assertSame(0, (int) $fevereiro[0]['parcela_parcial']);
    }

    public function testGetSinteticoReportSeparaPagamentosDoSaldoRestante(): void
    {
        [$naturezaId, $centroPaiId, $centroId, $fornecedorId] = $this->createReportDimensions();

        $bancoId = $this->createBank(100000);
        $contaId = $this->createConta(30000, [
            'natureza_id' => $naturezaId,
            'centro_de_custo_id' => $centroId,
            'fornecedor_id' => $fornecedorId,
        ]);
        $parcial = $this->createParcela($contaId, 10000, '2026-01-10');
        $this->createParcela($contaId, 20000, '2026-02-10');

        PagamentoService::pagarParcela($parcial, $bancoId, '2026-01-05', 4000, 500, null);

        $pago = Database::getSinteticoReport('2026-01-01', '2026-01-31', 'all', 'all', 'all', 'paid');

        $this->assertCount(1, $pago);
        $this->assertSame($naturezaId, (int) $pago[0]['natureza_id']);
        $this->assertSame($centroId, (int) $pago[0]['centro_id']);
        $this->assertSame($centroPaiId, (int) $pago[0]['pai_id']);
        $this->assertSame('Centro pai', $pago[0]['pai_nome']);
        $this->assertSame(1, (int) $pago[0]['quantidade']);
        $this->assertSame(4500, (int) $pago[0]['total']);

        $aPagarJaneiro = Database::getSinteticoReport('2026-01-01', '2026-01-31', 'all', 'all', 'all', 'unpaid');

        $this->assertCount(1, $aPagarJaneiro);
        $this->assertSame(1, (int) $aPagarJaneiro[0]['quantidade']);
        $this->assertSame(6000, (int) $aPagarJaneiro[0]['total']);

        $aPagarFevereiro = Database::getSinteticoReport('2026-02-01', '2026-02-28', 'all', 'all', 'all', 'unpaid');

        $this->assertCount(1, $aPagarFevereiro);
        $this->assertSame(1, (int) $aPagarFevereiro[0]['quantidade']);
        $this->assertSame(20000, (int) $aPagarFevereiro[0]['total']);
    }

    public function testGetSinteticoGeralReportSeparaPagamentosDoSaldoRestante(): void
    {
        [$naturezaId, $centroPaiId, $centroId, $fornecedorId] = $this->createReportDimensions();

        $bancoId = $this->createBank(100000);
        $contaId = $this->createConta(30000, [
            'natureza_id' => $naturezaId,
            'centro_de_custo_id' => $centroId,
            'fornecedor_id' => $fornecedorId,
        ]);
        $parcial = $this->createParcela($contaId, 10000, '2026-01-10');
        $this->createParcela($contaId, 20000, '2026-02-10');

        PagamentoService::pagarParcela($parcial, $bancoId, '2026-01-05', 4000, 500, null);

        $pago = Database::getSinteticoGeralReport('2026-01-01', '2026-01-31', 'all', 'all', 'all', 'paid');

        $this->assertCount(1, $pago);
        $this->assertSame($centroPaiId, (int) $pago[0]['centro_id']);
        $this->assertSame('Centro pai', $pago[0]['centro_nome']);
        $this->assertSame(1, (int) $pago[0]['quantidade']);
        $this->assertSame(4500, (int) $pago[0]['total']);

        $aPagar = Database::getSinteticoGeralReport('2026-01-01', '2026-01-31', 'all', 'all', 'all', 'unpaid');

        $this->assertCount(1, $aPagar);
        $this->assertSame($centroPaiId, (int) $aPagar[0]['centro_id']);
        $this->assertSame(1, (int) $aPagar[0]['quantidade']);
        $this->assertSame(6000, (int) $aPagar[0]['total']);
    }

    public function testGetReportRespeitaFiltrosDeNaturezaCentroEFornecedor(): void
    {
        [$naturezaA, $centroPaiId, $centroFilhoId, $fornecedorA] = $this->createReportDimensions();
        $naturezaB = $this->createNatureza('Natureza B');
        $centroB = $this->createCentroDeCusto('Centro B');
        $fornecedorB = $this->createFornecedor('Fornecedor B');

        $bancoId = $this->createBank(100000);

        $contaA = $this->createConta(10000, [
            'natureza_id' => $naturezaA,
            'centro_de_custo_id' => $centroFilhoId,
            'fornecedor_id' => $fornecedorA,
        ]);
        $contaB = $this->createConta(20000, [
            'natureza_id' => $naturezaB,
            'centro_de_custo_id' => $centroB,
            'fornecedor_id' => $fornecedorB,
        ]);

        $parcelaA = $this->createParcela($contaA, 10000, '2026-01-10');
        $parcelaB = $this->createParcela($contaB, 20000, '2026-01-10');

        PagamentoService::pagarParcela($parcelaA, $bancoId, '2026-01-05', null, 0, null);
        PagamentoService::pagarParcela($parcelaB, $bancoId, '2026-01-06', null, 0, null);

        $porNatureza = Database::getReport('2026-01-01', '2026-01-31', (string) $naturezaA, 'all', 'all', 'paid');
        $this->assertCount(1, $porNatureza);
        $this->assertSame(10000, (int) $porNatureza[0]['valor_total_em_centavos']);

        $porCentroPai = Database::getReport('2026-01-01', '2026-01-31', 'all', (string) $centroPaiId, 'all', 'paid');
        $this->assertCount(1, $porCentroPai);
        $this->assertSame(10000, (int) $porCentroPai[0]['valor_total_em_centavos']);

        $porFornecedor = Database::getReport('2026-01-01', '2026-01-31', 'all', 'all', (string) $fornecedorA, 'paid');
        $this->assertCount(1, $porFornecedor);
        $this->assertSame(10000, (int) $porFornecedor[0]['valor_total_em_centavos']);

        $todos = Database::getReport('2026-01-01', '2026-01-31', 'all', 'all', 'all', 'paid');
        $this->assertCount(2, $todos);
    }

    public function testGetReportInativadasContinuaPorParcelaComValorCheio(): void
    {
        $contaId = $this->createConta(10000, ['enabled' => 0]);
        $this->createParcela($contaId, 10000, '2026-01-10', [
            'juros_em_centavos' => 500,
            'valor_pago_em_centavos' => 4500,
            'data_pagamento' => '2026-01-05',
        ]);

        $rows = Database::getReport('2026-01-01', '2026-01-31', 'all', 'all', 'all', 'inativadas');

        $this->assertCount(1, $rows);
        $this->assertSame(10500, (int) $rows[0]['valor_total_em_centavos']);
        $this->assertSame(4500, (int) $rows[0]['valor_pago_em_centavos']);
        $this->assertSame(6000, (int) $rows[0]['valor_restante_em_centavos']);
        $this->assertSame(1, (int) $rows[0]['parcela_parcial']);
    }

    public function testGetReportMarcaParcelaParcial(): void
    {
        $bancoId = $this->createBank(100000);
        $contaId = $this->createConta(30000);

        $parcial = $this->createParcela($contaId, 10000, '2026-01-10');
        $quitada = $this->createParcela($contaId, 20000, '2026-01-20');
        $this->createParcela($contaId, 30000, '2026-01-30');

        PagamentoService::pagarParcela($parcial, $bancoId, '2026-01-05', 4000, 0, null);
        PagamentoService::pagarParcela($quitada, $bancoId, '2026-01-15', null, 0, null);

        $aPagar = $this->indexByNumeroParcela(
            Database::getReport('2026-01-01', '2026-01-31', 'all', 'all', 'all', 'unpaid')
        );

        $this->assertSame(1, (int) $aPagar[1]['parcela_parcial']);
        $this->assertSame(0, (int) $aPagar[3]['parcela_parcial']);

        $pagas = $this->indexByNumeroParcela(
            Database::getReport('2026-01-01', '2026-01-31', 'all', 'all', 'all', 'paid')
        );

        $this->assertSame(1, (int) $pagas[1]['parcela_parcial']);
        $this->assertSame(0, (int) $pagas[2]['parcela_parcial']);
    }

    /**
     * @return array{int, int, int, int} natureza, centro pai, centro filho, fornecedor
     */
    private function createReportDimensions(): array
    {
        $naturezaId = $this->createNatureza('Natureza A');
        $centroPaiId = $this->createCentroDeCusto('Centro pai');
        $centroId = $this->createCentroDeCusto('Centro filho', $centroPaiId);
        $fornecedorId = $this->createFornecedor('Fornecedor A');

        return [$naturezaId, $centroPaiId, $centroId, $fornecedorId];
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    private function indexByNumeroParcela(array $rows): array
    {
        $indexed = [];

        foreach ($rows as $row) {
            $indexed[(int) $row['numero_parcela']] = $row;
        }

        return $indexed;
    }

    private function sortByPaymentDate(array $rows): array
    {
        usort(
            $rows,
            static fn(array $a, array $b): int => strcmp((string) $a['data_pagamento'], (string) $b['data_pagamento'])
        );

        return $rows;
    }
}
