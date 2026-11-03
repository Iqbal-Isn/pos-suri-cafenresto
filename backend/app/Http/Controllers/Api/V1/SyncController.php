<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SyncController extends Controller
{
    public function __construct(
        protected OrderController $orderController,
        protected PaymentController $paymentController,
    ) {}

    public function batch(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'orders' => ['required', 'array', 'min:1'],
            'orders.*.client_uuid' => ['required', 'string', 'max:100'],
            'orders.*.order_type' => ['required', 'string', 'in:dine_in,take_away,delivery,pre_order'],
            'orders.*.table_id' => ['nullable', 'integer', 'exists:tables,id'],
            'orders.*.customer_name' => ['nullable', 'string'],
            'orders.*.guest_count' => ['nullable', 'integer'],
            'orders.*.items' => ['required', 'array', 'min:1'],
            'orders.*.payments' => ['nullable', 'array'],
        ]);

        $syncedOrders = [];

        foreach ($validated['orders'] as $orderPayload) {
            $existing = Order::where('client_uuid', $orderPayload['client_uuid'])->first();

            if (! $existing) {
                $subRequest = Request::create('/api/v1/orders', 'POST', $orderPayload);
                $subRequest->setUserResolver(fn () => $request->user());
                $orderResponse = $this->orderController->store($subRequest);
                $orderData = $orderResponse->getData(true)['data'];
                $order = Order::findOrFail($orderData['id']);
            } else {
                $order = $existing;
            }

            if (! empty($orderPayload['payments'])) {
                $payRequest = Request::create("/api/v1/orders/{$order->id}/payments", 'POST', [
                    'payments' => $orderPayload['payments'],
                ]);
                $payRequest->setUserResolver(fn () => $request->user());
                $this->paymentController->store($payRequest, $order);
            }

            $syncedOrders[] = $order->fresh(['items.modifiers', 'payments.paymentMethod']);
        }

        return response()->json([
            'synced_count' => count($syncedOrders),
            'orders' => $syncedOrders,
        ]);
    }
}
