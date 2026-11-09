<?php

declare(strict_types=1);

namespace App\Tests;

use App\Models\Parcela;
use PHPUnit\Framework\TestCase;

final class ParcelaTest extends TestCase
{
    public function testParcelaAbertaNaoEParcial(): void
    {
        $parcela = new Parcela(1, 1, 10000, '2026-01-10', null, 1, null, false);

        $this->assertSame(10000, $parcela->getValorRestante());
        $this->assertFalse($parcela->isParcial());
    }

    public function testParcelaComPagamentoParcialTemRestante(): void
    {
        $parcela = new Parcela(1, 1, 10000, '2026-01-10', '2026-01-05', 1, 2, false, 500, 4000);

        $this->assertSame(6500, $parcela->getValorRestante());
        $this->assertTrue($parcela->isParcial());
    }

    public function testParcelaPagaNaoEParcial(): void
    {
        $parcela = new Parcela(1, 1, 10000, '2026-01-10', '2026-01-05', 1, 2, true, 500, 10500);

        $this->assertSame(0, $parcela->getValorRestante());
        $this->assertFalse($parcela->isParcial());
    }

    public function testJurosAumentamORestante(): void
    {
        $parcela = new Parcela(1, 1, 10000, '2026-01-10', '2026-01-05', 1, 2, false, 500, 500);

        $this->assertSame(10000, $parcela->getValorRestante());
        $this->assertTrue($parcela->isParcial());
    }
}
