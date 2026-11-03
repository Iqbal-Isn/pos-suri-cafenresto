<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\Shift;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ReportController extends Controller
{
    public function sales(Request $request): Response
    {
        $outletId = $request->integer('outlet_id') ?: $request->user()->outlet_id;

        $ordersQuery = Order::where('outlet_id', $outletId)
            ->where('status', 'paid');

        if ($request->filled('from')) {
            $ordersQuery->whereDate('paid_at', '>=', $request->string('from'));
        }
        if ($request->filled('to')) {
            $ordersQuery->whereDate('paid_at', '<=', $request->string('to'));
        }

        $orders = $ordersQuery->get();

        $totalOrders = $orders->count();
        $grossSales = (int) $orders->sum('gross_subtotal');
        $totalDiscounts = (int) $orders->sum('item_discount_total') + (int) $orders->sum('discount_amount');
        $totalServiceCharge = (int) $orders->sum('service_charge_amount');
        $totalTax = (int) $orders->sum('tax_amount');
        $grandTotalSales = (int) $orders->sum('grand_total');
        $totalRefunds = (int) Refund::where('outlet_id', $outletId)->sum('amount');
        $netSales = $grandTotalSales - $totalRefunds;

        $byOrderType = $orders->groupBy('order_type')->map(fn ($group) => [
            'count' => $group->count(),
            'total' => (int) $group->sum('grand_total'),
        ]);

        $byPaymentMethod = Payment::with('paymentMethod')
            ->where('outlet_id', $outletId)
            ->get()
            ->groupBy(fn (Payment $p) => $p->paymentMethod?->name ?? 'Unknown')
            ->map(fn ($group) => [
                'count' => $group->count(),
                'total' => (int) $group->sum('amount'),
            ]);

        if ($request->string('export')->toString() === 'csv') {
            $lines = [
                'order_number,order_type,subtotal,discount_amount,service_charge,tax_amount,grand_total,paid_at',
            ];
            foreach ($orders as $o) {
                $lines[] = implode(',', [
                    $o->order_number,
                    $o->order_type,
                    $o->subtotal,
                    $o->discount_amount,
                    $o->service_charge_amount,
                    $o->tax_amount,
                    $o->grand_total,
                    $o->paid_at?->toDateTimeString() ?? '',
                ]);
            }

            return response(implode("\n", $lines), 200, [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="sales-report.csv"',
            ]);
        }

        return response()->json([
            'summary' => [
                'total_orders' => $totalOrders,
                'gross_sales' => $grossSales,
                'total_discounts' => $totalDiscounts,
                'total_service_charge' => $totalServiceCharge,
                'total_tax' => $totalTax,
                'grand_total_sales' => $grandTotalSales,
                'total_refunds' => $totalRefunds,
                'net_sales' => $netSales,
            ],
            'by_order_type' => $byOrderType,
            'by_payment_method' => $byPaymentMethod,
        ]);
    }

    public function products(Request $request): JsonResponse
    {
        $outletId = $request->integer('outlet_id') ?: $request->user()->outlet_id;

        $items = OrderItem::with('product.category')
            ->where('is_voided', false)
            ->whereHas('order', fn ($q) => $q->where('outlet_id', $outletId)->where('status', 'paid'))
            ->get();

        $byProduct = $items->groupBy('product_id')->map(function ($group) {
            $first = $group->first();

            return [
                'product_id' => $first->product_id,
                'product_name' => $first->product_name,
                'category_name' => $first->product?->category?->name,
                'total_qty' => (int) $group->sum('quantity'),
                'total_revenue' => (int) $group->sum('subtotal'),
            ];
        })->values();

        return response()->json(['data' => $byProduct]);
    }

    public function shifts(Request $request): JsonResponse
    {
        $outletId = $request->integer('outlet_id') ?: $request->user()->outlet_id;

        $shifts = Shift::with(['user', 'cashMovements'])
            ->where('outlet_id', $outletId)
            ->latest('opened_at')
            ->get();

        return response()->json(['data' => $shifts]);
    }

    public function voids(Request $request): JsonResponse
    {
        $outletId = $request->integer('outlet_id') ?: $request->user()->outlet_id;

        $voidedItems = OrderItem::with(['order', 'product'])
            ->where('is_voided', true)
            ->whereHas('order', fn ($q) => $q->where('outlet_id', $outletId))
            ->get();

        $refunds = Refund::with(['order', 'payment.paymentMethod', 'requester', 'approver'])
            ->where('outlet_id', $outletId)
            ->get();

        $auditLogs = AuditLog::with(['user', 'authorizer'])
            ->where('outlet_id', $outletId)
            ->latest()
            ->limit(100)
            ->get();

        return response()->json([
            'voided_items' => $voidedItems,
            'refunds' => $refunds,
            'audit_logs' => $auditLogs,
        ]);
    }
}
