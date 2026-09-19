<?php

namespace Tests\Unit;

use App\Services\ExchangeRateService;
use Tests\TestCase;

class ExchangeRateXeParseTest extends TestCase
{
    public function test_parses_xe_spaced_mid_market_rate(): void
    {
        $html = '1.00 USD = 1.39 887029 CAD Mid-market rate at 22:37 UTC';
        $rate = (new ExchangeRateService)->parseXeUsdCadRate($html);

        $this->assertSame(1.39887, $rate);
    }

    public function test_parses_compact_one_usd_equals_cad(): void
    {
        $html = '1 USD = 1.39887 CAD';
        $rate = (new ExchangeRateService)->parseXeUsdCadRate($html);

        $this->assertSame(1.39887, $rate);
    }

    public function test_converts_cad_amount_to_usd(): void
    {
        $usd = (new ExchangeRateService)->convertCadToUsd(139.887, 1.39887);

        $this->assertSame(100.0, $usd);
    }

    public function test_rejects_implausible_xe_rate(): void
    {
        $this->assertNull((new ExchangeRateService)->parseXeUsdCadRate('1 USD = 9.12 CAD'));
    }
}
