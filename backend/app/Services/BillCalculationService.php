<?php

namespace App\Services;

class BillCalculationService
{
    /**
     * Calculate bill amounts in integer Rupiah (BR-01 & BR-03).
     *
     * @param  array<int, array<string, int|float|string|null>>  $items
     * @param  array{type?: string, value?: int|float}|null  $orderDiscount
     * @param  array{rate?: int|float, inclusive?: bool, service_charge_rate?: int|float, rounding_multiple?: int}  $taxConfig
     * @return array{
     *     gross_subtotal: int,
     *     item_discount_total: int,
     *     subtotal: int,
     *     order_discount_amount: int,
     *     service_charge_amount: int,
     *     tax_amount: int,
     *     rounding_amount: int,
     *     grand_total: int,
     *     items: array<int, array<string, mixed>>
     * }
     */
    public function calculate(array $items, ?array $orderDiscount = null, array $taxConfig = []): array
    {
        $grossSubtotal = 0;
        $itemDiscountTotal = 0;
        $calculatedItems = [];

        foreach ($items as $item) {
            if (! empty($item['is_voided'])) {
                continue;
            }

            $unitPrice = (int) ($item['unit_price'] ?? 0);
            $variantAdjustment = (int) ($item['variant_price_adjustment'] ?? 0);
            $modifiersPrice = (int) ($item['modifiers_price'] ?? 0);
            $quantity = max(1, (int) ($item['quantity'] ?? 1));

            $effectiveUnitPrice = $unitPrice + $variantAdjustment + $modifiersPrice;
            $lineGross = $effectiveUnitPrice * $quantity;

            $discountType = $item['discount_type'] ?? null;
            $discountValue = (float) ($item['discount_value'] ?? 0);
            $lineDiscount = 0;

            if ($discountType === 'percentage' && $discountValue > 0) {
                $lineDiscount = (int) round($lineGross * ($discountValue / 100));
            } elseif ($discountType === 'fixed' && $discountValue > 0) {
                $lineDiscount = (int) round($discountValue);
            }

            $lineDiscount = min($lineGross, max(0, $lineDiscount));
            $lineSubtotal = $lineGross - $lineDiscount;

            $grossSubtotal += $lineGross;
            $itemDiscountTotal += $lineDiscount;

            $calculatedItems[] = array_merge($item, [
                'effective_unit_price' => $effectiveUnitPrice,
                'line_gross' => $lineGross,
                'line_discount' => $lineDiscount,
                'line_subtotal' => $lineSubtotal,
            ]);
        }

        $subtotal = $grossSubtotal - $itemDiscountTotal;

        $orderDiscountAmount = 0;
        if ($orderDiscount && ! empty($orderDiscount['type']) && ! empty($orderDiscount['value'])) {
            $discVal = (float) $orderDiscount['value'];
            if ($orderDiscount['type'] === 'percentage' && $discVal > 0) {
                $orderDiscountAmount = (int) round($subtotal * ($discVal / 100));
            } elseif ($orderDiscount['type'] === 'fixed' && $discVal > 0) {
                $orderDiscountAmount = (int) round($discVal);
            }
        }
        $orderDiscountAmount = min($subtotal, max(0, $orderDiscountAmount));

        $netAfterDiscount = $subtotal - $orderDiscountAmount;

        $serviceChargeRate = (float) ($taxConfig['service_charge_rate'] ?? 0);
        $taxRate = (float) ($taxConfig['rate'] ?? 0);
        $taxInclusive = (bool) ($taxConfig['inclusive'] ?? false);
        $roundingMultiple = (int) ($taxConfig['rounding_multiple'] ?? 0);

        $serviceChargeAmount = $serviceChargeRate > 0
            ? (int) round($netAfterDiscount * ($serviceChargeRate / 100))
            : 0;

        $taxBase = $netAfterDiscount + $serviceChargeAmount;

        if ($taxInclusive && $taxRate > 0) {
            $taxAmount = $taxBase - (int) round($taxBase / (1 + ($taxRate / 100)));
            $preRoundingTotal = $taxBase;
        } else {
            $taxAmount = $taxRate > 0
                ? (int) round($taxBase * ($taxRate / 100))
                : 0;
            $preRoundingTotal = $taxBase + $taxAmount;
        }

        $roundingAmount = 0;
        $grandTotal = $preRoundingTotal;

        if ($roundingMultiple > 0) {
            $grandTotal = (int) (round($preRoundingTotal / $roundingMultiple) * $roundingMultiple);
            $roundingAmount = $grandTotal - $preRoundingTotal;
        }

        return [
            'gross_subtotal' => $grossSubtotal,
            'item_discount_total' => $itemDiscountTotal,
            'subtotal' => $subtotal,
            'order_discount_amount' => $orderDiscountAmount,
            'service_charge_amount' => $serviceChargeAmount,
            'tax_amount' => $taxAmount,
            'rounding_amount' => $roundingAmount,
            'grand_total' => $grandTotal,
            'items' => $calculatedItems,
        ];
    }
}
