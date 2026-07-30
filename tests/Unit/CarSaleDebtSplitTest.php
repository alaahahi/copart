<?php

namespace Tests\Unit;

use App\Services\CarService;
use PHPUnit\Framework\TestCase;

class CarSaleDebtSplitTest extends TestCase
{
    private CarService $cars;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cars = new CarService;
    }

    public function test_first_sale_credits_revenue_with_profit_only(): void
    {
        $split = $this->cars->computeSaleDebtSplit(0, 7815, 6000);

        $this->assertSame(7815.0, $split['sales_delta']);
        $this->assertSame(1815.0, $split['revenue_delta']);
        $this->assertSame(6000.0, $split['cost_recovery_delta']);
        $this->assertSame(
            $split['sales_delta'],
            round($split['cost_recovery_delta'] + $split['revenue_delta'], 2)
        );
    }

    public function test_sales_increase_after_first_recognition_is_all_profit(): void
    {
        $split = $this->cars->computeSaleDebtSplit(7815, 9457, 6000);

        $this->assertSame(1642.0, $split['sales_delta']);
        $this->assertSame(1642.0, $split['revenue_delta']);
        $this->assertSame(0.0, $split['cost_recovery_delta']);
    }

    public function test_clearing_sale_reverses_cost_and_profit(): void
    {
        $split = $this->cars->computeSaleDebtSplit(7815, 0, 6000);

        $this->assertSame(-7815.0, $split['sales_delta']);
        $this->assertSame(-1815.0, $split['revenue_delta']);
        $this->assertSame(-6000.0, $split['cost_recovery_delta']);
    }

    public function test_loss_sale_debits_revenue_conceptually_via_negative_profit_delta(): void
    {
        $split = $this->cars->computeSaleDebtSplit(0, 5000, 7000);

        $this->assertSame(5000.0, $split['sales_delta']);
        $this->assertSame(-2000.0, $split['revenue_delta']);
        $this->assertSame(7000.0, $split['cost_recovery_delta']);
    }

    public function test_screenshot_amounts_match_full_sales_when_prior_total_s_was_zero(): void
    {
        // Bug: full 7815 / 9457 hit 4100; profit-only path recovers cost.
        $a = $this->cars->computeSaleDebtSplit(0, 7815, 5000);
        $b = $this->cars->computeSaleDebtSplit(0, 9457, 7000);

        $this->assertSame(2815.0, $a['revenue_delta']);
        $this->assertSame(2457.0, $b['revenue_delta']);
        $this->assertLessThan($a['sales_delta'], $a['revenue_delta']);
        $this->assertLessThan($b['sales_delta'], $b['revenue_delta']);
    }
}
