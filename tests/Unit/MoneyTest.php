<?php

namespace Tests\Unit;

use App\Support\Money;
use PHPUnit\Framework\TestCase;

class MoneyTest extends TestCase
{
    public function test_parses_amounts_to_baisa(): void
    {
        $this->assertSame(12345, Money::toBaisa('12.345'));
        $this->assertSame(12346, Money::toBaisa('12.3455'));
        $this->assertSame(1234500, Money::toBaisa('1,234.5'));
        $this->assertSame(-500, Money::toBaisa('-0.5'));
        $this->assertSame(0, Money::toBaisa(''));
        $this->assertSame(100, Money::toBaisa('.1'));
    }

    public function test_formats_baisa(): void
    {
        $this->assertSame('1,234.567', Money::format(1234567));
        $this->assertSame('0.050', Money::format(50));
        $this->assertSame('-12.000', Money::format(-12000));
        $this->assertSame('12.345', Money::toDecimal(12345));
        $this->assertSame('100.000 Dr', Money::drCr(100000));
        $this->assertSame('5.000 Cr', Money::drCr(-5000));
    }

    public function test_oman_vat_is_rounded_to_nearest_baisa(): void
    {
        $this->assertSame(5000, Money::vat(100000, '5'));      // 100.000 -> 5.000
        $this->assertSame(617, Money::vat(12345, '5.00'));    // 12.345 * 5% = 0.61725 -> 0.617
        $this->assertSame(1, Money::vat(10, '5'));            // 0.010 * 5% = 0.0005 -> 0.001
        $this->assertSame(0, Money::vat(100000, '0'));
    }

    public function test_rejects_garbage(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Money::toBaisa('abc');
    }
}
