<?php

declare(strict_types=1);

namespace App\Tests;

use App\Controllers\Services\PagamentoService;
use App\Models\Banco;
use App\Tests\Support\DatabaseTestCase;
use App\Tests\Support\Fixtures;

final class BancoSaldoTest extends DatabaseTestCase
{
    use Fixtures;

    public function testSaldoEmDataDescontaPagamentosESomaDepositosFuturos(): void
    {
        $usuarioId = $this->createUser();
        $bancoId = $this->createBank(100000);
        $contaId = $this->createConta(10000);
        $parcelaId = $this->createParcela($contaId, 10000);

        PagamentoService::pagarParcela($parcelaId, $bancoId, '2026-03-10', 4000, 500, null);

        $this->createDeposito($usuarioId, $bancoId, 2000, '2026-03-12 00:00:00');
        $stmt = $this->pdo->prepare('UPDATE banco SET saldo_em_centavos = saldo_em_centavos + :valor WHERE id = :id');
        $stmt->bindValue(':valor', 2000, \PDO::PARAM_INT);
        $stmt->bindValue(':id', $bancoId, \PDO::PARAM_INT);
        $stmt->execute();

        $this->assertSame(97500, $this->fetchBanco($bancoId)['saldo_em_centavos']);

        $this->assertSame(100000, Banco::getSaldoEmData($bancoId, '2026-03-09'));
        $this->assertSame(95500, Banco::getSaldoEmData($bancoId, '2026-03-10'));
        $this->assertSame(95500, Banco::getSaldoEmData($bancoId, '2026-03-11'));
        $this->assertSame(97500, Banco::getSaldoEmData($bancoId, '2026-03-12'));
        $this->assertSame(97500, Banco::getSaldoEmData($bancoId, '2026-03-13'));
    }

    public function testSaldoEmDataInvalidaRetornaZero(): void
    {
        $bancoId = $this->createBank(100000);

        $this->assertSame(0, Banco::getSaldoEmData($bancoId, 'data-invalida'));
    }

    public function testSaldoEmDataIgnoraPagamentosDeCobrancasInativas(): void
    {
        $bancoId = $this->createBank(93000);
        $contaInativa = $this->createConta(10000, ['enabled' => 0]);
        $contaAtiva = $this->createConta(10000);

        $parcelaContaInativa = $this->createParcela($contaInativa, 10000, '2026-03-10');
        $parcelaInativa = $this->createParcela($contaAtiva, 10000, '2026-03-10', ['enabled' => 0]);

        $this->createPagamento($parcelaContaInativa, $bancoId, 7000, 0, '2026-03-10');
        $this->createPagamento($parcelaInativa, $bancoId, 3000, 0, '2026-03-10');

        // Pagamentos de cobranças inativas não entram na reconstrução do saldo.
        $this->assertSame(93000, Banco::getSaldoEmData($bancoId, '2026-03-09'));
    }
}
