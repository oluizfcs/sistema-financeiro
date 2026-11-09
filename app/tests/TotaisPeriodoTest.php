<?php

declare(strict_types=1);

namespace App\Tests;

use App\Models\CentroDeCusto;
use App\Models\Fornecedor;
use App\Models\Natureza;
use App\Tests\Support\DatabaseTestCase;
use App\Tests\Support\Fixtures;

final class TotaisPeriodoTest extends DatabaseTestCase
{
    use Fixtures;

    public function testCentroDeCustoTotaisIgnoramCobrancasInativasEUsamRestante(): void
    {
        $this->criarCenario();

        $this->assertTotais(
            static fn(string $status): array => CentroDeCusto::getTotalsByPeriod('2026-01-01', '2026-01-31', $status),
            'Centro ativo'
        );
    }

    public function testCentroDeCustoAgrupadoIgnoraCobrancasInativasEUsaRestante(): void
    {
        $this->criarCenario();

        $this->assertTotais(
            static fn(string $status): array => CentroDeCusto::getGroupedTotalsByPeriod('2026-01-01', '2026-01-31', $status),
            'Centro ativo'
        );
    }

    public function testFornecedorTotaisIgnoramCobrancasInativasEUsamRestante(): void
    {
        $this->criarCenario();

        $this->assertTotais(
            static fn(string $status): array => Fornecedor::getTotalsByPeriod('2026-01-01', '2026-01-31', $status),
            'Fornecedor ativo'
        );
    }

    public function testNaturezaTotaisIgnoramCobrancasInativasEUsamRestante(): void
    {
        $this->criarCenario();

        $this->assertTotais(
            static fn(string $status): array => Natureza::getTotalsByPeriod('2026-01-01', '2026-01-31', $status),
            'Natureza ativa'
        );
    }

    private function assertTotais(callable $consulta, string $nomeEsperado): void
    {
        $aPagar = $consulta('a_pagar');
        $this->assertCount(1, $aPagar, 'Apenas a dimensão ativa deve aparecer em a_pagar');
        $this->assertSame($nomeEsperado, $aPagar[0]['nome']);
        $this->assertSame(11000, (int) $aPagar[0]['total']);

        $pagas = $consulta('pagas');
        $this->assertCount(1, $pagas, 'Apenas a dimensão ativa deve aparecer em pagas');
        $this->assertSame($nomeEsperado, $pagas[0]['nome']);
        $this->assertSame(14000, (int) $pagas[0]['total']);

        $todas = $consulta('todas');
        $this->assertCount(1, $todas, 'Apenas a dimensão ativa deve aparecer em todas');
        $this->assertSame($nomeEsperado, $todas[0]['nome']);
        $this->assertSame(25000, (int) $todas[0]['total']);
    }

    private function criarCenario(): void
    {
        $bancoId = $this->createBank(1000000);

        $naturezaAtiva = $this->createNatureza('Natureza ativa');
        $fornecedorAtivo = $this->createFornecedor('Fornecedor ativo');
        $centroAtivo = $this->createCentroDeCusto('Centro ativo');

        $contaAtiva = $this->createConta(25000, [
            'centro_de_custo_id' => $centroAtivo,
            'natureza_id' => $naturezaAtiva,
            'fornecedor_id' => $fornecedorAtivo,
        ]);

        // Quitada: 10.000 pagos pelo livro caixa.
        $quitada = $this->createParcela($contaAtiva, 10000, '2026-01-15', [
            'valor_pago_em_centavos' => 10000,
            'data_pagamento' => '2026-01-15',
            'banco_id' => $bancoId,
            'paid' => 1,
        ]);
        $this->createPagamento($quitada, $bancoId, 10000, 0, '2026-01-15');

        // Parcial: 4.000 pagos, restante 6.000.
        $parcial = $this->createParcela($contaAtiva, 10000, '2026-01-20', [
            'valor_pago_em_centavos' => 4000,
            'data_pagamento' => '2026-01-20',
            'banco_id' => $bancoId,
        ]);
        $this->createPagamento($parcial, $bancoId, 4000, 0, '2026-01-20');

        // Aberta: 5.000 restantes.
        $this->createParcela($contaAtiva, 5000, '2026-01-10');

        // Conta inativa não deve entrar em nenhum total.
        $contaInativa = $this->createConta(20000, [
            'enabled' => 0,
            'centro_de_custo_id' => $this->createCentroDeCusto('Centro inativo'),
            'natureza_id' => $this->createNatureza('Natureza inativa'),
            'fornecedor_id' => $this->createFornecedor('Fornecedor inativo'),
        ]);
        $parcelaContaInativa = $this->createParcela($contaInativa, 20000, '2026-01-10');
        $this->createPagamento($parcelaContaInativa, $bancoId, 20000, 0, '2026-01-15');

        // Parcela inativa em conta ativa também não deve entrar.
        $parcelaInativa = $this->createParcela($contaAtiva, 30000, '2026-01-10', ['enabled' => 0]);
        $this->createPagamento($parcelaInativa, $bancoId, 30000, 0, '2026-01-15');
    }
}
