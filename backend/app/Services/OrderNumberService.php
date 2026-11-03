<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Outlet;
use Carbon\Carbon;

class OrderNumberService
{
    public function generate(Outlet $outlet, ?Carbon $date = null): string
    {
        $date = $date ?: now();
        $datePart = $date->format('Ymd');
        $prefix = "{$outlet->code}-{$datePart}-";

        $lastOrder = Order::where('outlet_id', $outlet->id)
            ->where('order_number', 'like', "{$prefix}%")
            ->orderByDesc('order_number')
            ->first();

        $nextSeq = 1;
        if ($lastOrder) {
            $parts = explode('-', $lastOrder->order_number);
            $lastSeq = (int) end($parts);
            $nextSeq = $lastSeq + 1;
        }

        return $prefix.str_pad((string) $nextSeq, 4, '0', STR_PAD_LEFT);
    }
}
