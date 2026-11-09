<?php

declare(strict_types=1);

namespace App\Tests;

use App\Controllers\Services\PagamentoService;
use App\Models\ParcelaPagamento;
use App\Tests\Support\DatabaseTestCase;
use App\Tests\Support\Fixtures;

final class ParcelaPagamentoModelTest extends DatabaseTestCase
{
    use Fixtures;

    public function testGetByContaRetornaLedgerComJoinsEOrdenacao(): void
    {
        $usuarioId = $this->createUser('Maria');
        $bancoA = $this->createBank(100000);
        $bancoB = $this->createBank(100000);
        $contaId = $this->createConta(20000);
        $parcelaA = $this->createParcela($contaId, 10000);
        $parcelaB = $this->createParcela($contaId, 10000);

        PagamentoService::pagarParcela($parcelaA, $bancoA, '2026-01-05', 3000, 200, $usuarioId);
        PagamentoService::pagarParcela($parcelaB, $bancoB, '2026-01-10', 5000, 0, $usuarioId);
        PagamentoService::pagarParcela($parcelaA, $bancoB, '2026-01-15', 1000, 0, $usuarioId);

        $pagamentos = ParcelaPagamento::getByConta($contaId);

        $this->assertCount(3, $pagamentos);

        $this->assertSame($parcelaA, $pagamentos[0]->getParcela_id());
        $this->assertSame($contaId, $pagamentos[0]->getConta_id());
        $this->assertSame(1, $pagamentos[0]->getNumero_parcela());
        $this->assertSame($bancoA, $pagamentos[0]->getBanco_id());
        $this->assertSame('Banco de teste', $pagamentos[0]->getBanco_nome());
        $this->assertSame(3000, $pagamentos[0]->getValor_em_centavos());
        $this->assertSame(200, $pagamentos[0]->getJuros_em_centavos());
        $this->assertSame(3200, $pagamentos[0]->getValor_total_em_centavos());
        $this->assertSame('2026-01-05', $pagamentos[0]->getData_pagamento());
        $this->assertSame($usuarioId, $pagamentos[0]->getUsuario_id());
        $this->assertSame('Maria', $pagamentos[0]->getUsuario_nome());

        $this->assertSame($parcelaB, $pagamentos[1]->getParcela_id());
        $this->assertSame(2, $pagamentos[1]->getNumero_parcela());
        $this->assertSame(5000, $pagamentos[1]->getValor_em_centavos());
        $this->assertSame('2026-01-10', $pagamentos[1]->getData_pagamento());

        $this->assertSame($parcelaA, $pagamentos[2]->getParcela_id());
        $this->assertSame($bancoB, $pagamentos[2]->getBanco_id());
        $this->assertSame(1000, $pagamentos[2]->getValor_em_centavos());
        $this->assertSame('2026-01-15', $pagamentos[2]->getData_pagamento());
    }

    public function testGetByParcelaFiltraPagamentos(): void
    {
        $bancoId = $this->createBank(100000);
        $contaId = $this->createConta(20000);
        $parcelaA = $this->createParcela($contaId, 10000);
        $parcelaB = $this->createParcela($contaId, 10000);

        PagamentoService::pagarParcela($parcelaA, $bancoId, '2026-01-05', 3000, 0, null);
        PagamentoService::pagarParcela($parcelaA, $bancoId, '2026-01-10', 2000, 0, null);
        PagamentoService::pagarParcela($parcelaB, $bancoId, '2026-01-15', 5000, 0, null);

        $pagamentosA = ParcelaPagamento::getByParcela($parcelaA);
        $pagamentosB = ParcelaPagamento::getByParcela($parcelaB);

        $this->assertCount(2, $pagamentosA);
        $this->assertSame([3000, 2000], array_map(
            static fn(ParcelaPagamento $pagamento): int => $pagamento->getValor_em_centavos(),
            $pagamentosA
        ));
        $this->assertCount(1, $pagamentosB);
        $this->assertSame(5000, $pagamentosB[0]->getValor_em_centavos());
    }

    public function testPagamentoSemUsuarioRetornaUsuarioNulo(): void
    {
        $bancoId = $this->createBank(100000);
        $contaId = $this->createConta(10000);
        $parcelaId = $this->createParcela($contaId, 10000);

        PagamentoService::pagarParcela($parcelaId, $bancoId, '2026-01-05', 4000, 0, null);

        $pagamentos = ParcelaPagamento::getByConta($contaId);

        $this->assertCount(1, $pagamentos);
        $this->assertNull($pagamentos[0]->getUsuario_id());
        $this->assertNull($pagamentos[0]->getUsuario_nome());
    }

    public function testGetByContaSemPagamentosRetornaVazio(): void
    {
        $contaId = $this->createConta(10000);
        $this->createParcela($contaId, 10000);

        $this->assertSame([], ParcelaPagamento::getByConta($contaId));
    }
}
