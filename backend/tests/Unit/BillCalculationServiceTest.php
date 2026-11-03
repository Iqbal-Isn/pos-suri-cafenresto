<?php

namespace Tests\Unit;

use App\Services\BillCalculationService;
use PHPUnit\Framework\TestCase;

class BillCalculationServiceTest extends TestCase
{
    public function test_calculates_exclusive_tax_service_charge_and_rounding_in_integer_rupiah(): void
    {
        $service = new BillCalculationService();

        $result = $service->calculate(
            items: [
                [
                    'unit_price' => 25000,
                    'variant_price_adjustment' => 3000,
                    'modifiers_price' => 5000,
                    'quantity' => 2,
                    'discount_type' => 'fixed',
                    'discount_value' => 2000,
                ],
                [
                    'unit_price' => 18500,
                    'variant_price_adjustment' => 0,
                    'modifiers_price' => 0,
                    'quantity' => 1,
                    'discount_type' => 'percentage',
                    'discount_value' => 10,
                ],
            ],
            orderDiscount: [
                'type' => 'percentage',
                'value' => 10,
            ],
            taxConfig: [
                'rate' => 10, // PB1 10%
                'inclusive' => false,
                'service_charge_rate' => 5, // Service 5%
                'rounding_multiple' => 100,
            ]
        );

        // Item 1: (25000 + 3000 + 5000) * 2 = 66000, minus 2000 = 64000
        // Item 2: 18500 * 1 = 18500, minus 10% (1850) = 16650
        // Subtotal after item discounts = 64000 + 16650 = 80650
        // Order discount 10% of 80650 = 8065 -> Net after discount = 72585
        // Service charge 5% of 72585 = 3629
        // Tax Base (net + service charge) = 72585 + 3629 = 76214
        // Tax PB1 10% of 76214 = 7621
        // Pre-rounding total = 72585 + 3629 + 7621 = 83835
        // Rounded to nearest 100 = 83800 (rounding_amount = -35)
        $this->assertSame(84500, $result['gross_subtotal']);
        $this->assertSame(3850, $result['item_discount_total']);
        $this->assertSame(80650, $result['subtotal']);
        $this->assertSame(8065, $result['order_discount_amount']);
        $this->assertSame(3629, $result['service_charge_amount']);
        $this->assertSame(7621, $result['tax_amount']);
        $this->assertSame(-35, $result['rounding_amount']);
        $this->assertSame(83800, $result['grand_total']);
    }

    public function test_calculates_inclusive_tax_correctly(): void
    {
        $service = new BillCalculationService();

        $result = $service->calculate(
            items: [
                [
                    'unit_price' => 55000,
                    'variant_price_adjustment' => 0,
                    'modifiers_price' => 0,
                    'quantity' => 2,
                ],
            ],
            orderDiscount: null,
            taxConfig: [
                'rate' => 10, // PB1 10% inclusive
                'inclusive' => true,
                'service_charge_rate' => 0,
                'rounding_multiple' => 0,
            ]
        );

        // Subtotal = 110000 (already includes 10% tax -> tax = 110000 - (110000 / 1.1) = 10000)
        $this->assertSame(110000, $result['subtotal']);
        $this->assertSame(10000, $result['tax_amount']);
        $this->assertSame(0, $result['rounding_amount']);
        $this->assertSame(110000, $result['grand_total']);
    }
}
