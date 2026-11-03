<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\DiningTable;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Refund;
use App\Models\Shift;
use App\Services\AuditLogService;
use App\Services\ManagerPinService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PaymentController extends Controller
{
    public function __construct(
        protected ManagerPinService $managerPinService,
        protected AuditLogService $auditLogService,
    ) {}

    public function methods(Request $request): JsonResponse
    {
        $outletId = $request->integer('outlet_id') ?: $request->user()->outlet_id;
        $methods = PaymentMethod::where('outlet_id', $outletId)
            ->where('is_active', true)
            ->get();

        return response()->json(['data' => $methods]);
    }

    public function store(Request $request, Order $order): JsonResponse
    {
        $validated = $request->validate([
            'payments' => ['required', 'array', 'min:1'],
            'payments.*.client_uuid' => ['nullable', 'string', 'max:100'],
            'payments.*.payment_method_id' => ['required', 'integer', 'exists:payment_methods,id'],
            'payments.*.amount' => ['required', 'integer', 'min:1'],
            'payments.*.tendered_amount' => ['nullable', 'integer', 'min:1'],
            'payments.*.reference_number' => ['nullable', 'string', 'max:100'],
        ]);

        $openShift = Shift::where('outlet_id', $order->outlet_id)
            ->where('status', 'open')
            ->latest('opened_at')
            ->first();

        $updatedOrder = DB::transaction(function () use ($order, $validated, $openShift, $request) {
            foreach ($validated['payments'] as $payInput) {
                if (! empty($payInput['client_uuid'])) {
                    $existing = Payment::where('client_uuid', $payInput['client_uuid'])->first();
                    if ($existing) {
                        continue;
                    }
                }

                $amount = (int) $payInput['amount'];
                $tendered = (int) ($payInput['tendered_amount'] ?? $amount);
                $change = max(0, $tendered - $amount);

                Payment::create([
                    'outlet_id' => $order->outlet_id,
                    'order_id' => $order->id,
                    'shift_id' => $openShift?->id ?? $order->shift_id,
                    'payment_method_id' => $payInput['payment_method_id'],
                    'user_id' => $request->user()->id,
                    'client_uuid' => $payInput['client_uuid'] ?? null,
                    'amount' => $amount,
                    'tendered_amount' => $tendered,
                    'change_amount' => $change,
                    'reference_number' => $payInput['reference_number'] ?? null,
                    'status' => 'completed',
                    'paid_at' => now(),
                ]);
            }

            $totalPaid = (int) Payment::where('order_id', $order->id)
                ->where('status', 'completed')
                ->sum('amount');

            $totalChange = (int) Payment::where('order_id', $order->id)
                ->where('status', 'completed')
                ->sum('change_amount');

            $isFullyPaid = $totalPaid >= $order->grand_total;

            $order->update([
                'paid_amount' => $totalPaid,
                'change_amount' => $totalChange,
                'status' => $isFullyPaid ? 'paid' : $order->status,
                'paid_at' => $isFullyPaid ? now() : $order->paid_at,
            ]);

            if ($isFullyPaid && $order->table_id) {
                DiningTable::where('id', $order->table_id)->update([
                    'status' => 'cleaning',
                    'merged_into_table_id' => null,
                ]);
            }

            return $order->fresh(['items.modifiers', 'payments.paymentMethod', 'outlet', 'table', 'user']);
        });

        return response()->json([
            'order' => $updatedOrder,
            'receipt' => [
                'outlet_name' => $updatedOrder->outlet?->name,
                'order_number' => $updatedOrder->order_number,
                'order_type' => $updatedOrder->order_type,
                'table_name' => $updatedOrder->table?->name,
                'cashier_name' => $updatedOrder->user?->name,
                'paid_at' => $updatedOrder->paid_at?->toIso8601String(),
                'items' => $updatedOrder->items->where('is_voided', false)->values(),
                'subtotal' => $updatedOrder->subtotal,
                'discount_amount' => $updatedOrder->discount_amount,
                'service_charge_amount' => $updatedOrder->service_charge_amount,
                'tax_amount' => $updatedOrder->tax_amount,
                'rounding_amount' => $updatedOrder->rounding_amount,
                'grand_total' => $updatedOrder->grand_total,
                'paid_amount' => $updatedOrder->paid_amount,
                'change_amount' => $updatedOrder->change_amount,
                'payments' => $updatedOrder->payments,
            ],
        ], 201);
    }

    public function refund(Request $request, Payment $payment): JsonResponse
    {
        $validated = $request->validate([
            'amount' => ['required', 'integer', 'min:1', 'max:'.$payment->amount],
            'reason' => ['required', 'string', 'min:3'],
            'manager_pin' => ['required', 'string'],
        ]);

        $authorizer = $this->managerPinService->verifyManagerPin($payment->outlet_id, $validated['manager_pin']);
        if (! $authorizer) {
            return response()->json([
                'message' => 'PIN Manager tidak valid. Refund ditolak.',
            ], 403);
        }

        $openShift = Shift::where('outlet_id', $payment->outlet_id)
            ->where('status', 'open')
            ->latest('opened_at')
            ->first();

        $refund = DB::transaction(function () use ($payment, $validated, $authorizer, $openShift, $request) {
            $refund = Refund::create([
                'outlet_id' => $payment->outlet_id,
                'order_id' => $payment->order_id,
                'payment_id' => $payment->id,
                'shift_id' => $openShift?->id ?? $payment->shift_id,
                'requested_by' => $request->user()->id,
                'approved_by' => $authorizer->id,
                'amount' => $validated['amount'],
                'reason' => $validated['reason'],
            ]);

            if ($validated['amount'] >= $payment->amount) {
                $payment->update(['status' => 'refunded']);
            }

            $this->auditLogService->record(
                outletId: $payment->outlet_id,
                userId: $request->user()->id,
                authorizedBy: $authorizer->id,
                action: 'payment.refund',
                auditableType: Payment::class,
                auditableId: $payment->id,
                reason: $validated['reason'],
                payload: [
                    'refund_id' => $refund->id,
                    'amount' => $validated['amount'],
                    'order_id' => $payment->order_id,
                ]
            );

            return $refund;
        });

        return response()->json(['data' => $refund], 201);
    }
}
