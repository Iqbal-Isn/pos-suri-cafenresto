<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\DiningTable;
use App\Models\KitchenTicket;
use App\Models\Modifier;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Outlet;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Shift;
use App\Models\TaxRule;
use App\Services\AuditLogService;
use App\Services\BillCalculationService;
use App\Services\ManagerPinService;
use App\Services\OrderNumberService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderController extends Controller
{
    public function __construct(
        protected BillCalculationService $billCalculator,
        protected OrderNumberService $orderNumberService,
        protected ManagerPinService $managerPinService,
        protected AuditLogService $auditLogService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $outletId = $request->integer('outlet_id') ?: $request->user()->outlet_id;

        $query = Order::with(['items.modifiers', 'table', 'user', 'payments.paymentMethod'])
            ->where('outlet_id', $outletId);

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        return response()->json([
            'data' => $query->latest()->get(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'client_uuid' => ['nullable', 'string', 'max:100'],
            'order_type' => ['required', 'string', 'in:dine_in,take_away,delivery,pre_order'],
            'table_id' => ['nullable', 'integer', 'exists:tables,id'],
            'customer_name' => ['nullable', 'string', 'max:150'],
            'guest_count' => ['nullable', 'integer', 'min:1'],
            'discount_type' => ['nullable', 'string', 'in:percentage,fixed'],
            'discount_value' => ['nullable', 'integer', 'min:0'],
            'manager_pin' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.product_variant_id' => ['nullable', 'integer', 'exists:product_variants,id'],
            'items.*.modifier_ids' => ['nullable', 'array'],
            'items.*.modifier_ids.*' => ['integer', 'exists:modifiers,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.discount_type' => ['nullable', 'string', 'in:percentage,fixed'],
            'items.*.discount_value' => ['nullable', 'integer', 'min:0'],
            'items.*.notes' => ['nullable', 'string'],
        ]);

        $outletId = $request->integer('outlet_id') ?: $request->user()->outlet_id;

        // Idempotent check via client_uuid (BR-09)
        if (! empty($validated['client_uuid'])) {
            $existingOrder = Order::with(['items.modifiers', 'kitchenTickets', 'table'])
                ->where('client_uuid', $validated['client_uuid'])
                ->first();
            if ($existingOrder) {
                return response()->json(['data' => $existingOrder]);
            }
        }

        // BR-04: Shift must be open
        $openShift = Shift::where('outlet_id', $outletId)
            ->where('status', 'open')
            ->latest('opened_at')
            ->first();

        if (! $openShift) {
            throw ValidationException::withMessages([
                'shift' => ['Shift wajib dibuka sebelum kasir dapat membuat transaksi (BR-04).'],
            ]);
        }

        $outlet = Outlet::findOrFail($outletId);

        $order = DB::transaction(function () use ($validated, $outlet, $openShift, $request) {
            $order = Order::create([
                'outlet_id' => $outlet->id,
                'shift_id' => $openShift->id,
                'table_id' => $validated['table_id'] ?? null,
                'user_id' => $request->user()->id,
                'client_uuid' => $validated['client_uuid'] ?? null,
                'order_number' => $this->orderNumberService->generate($outlet),
                'order_type' => $validated['order_type'],
                'customer_name' => $validated['customer_name'] ?? null,
                'guest_count' => $validated['guest_count'] ?? 1,
                'status' => 'open',
                'discount_type' => $validated['discount_type'] ?? null,
                'discount_value' => $validated['discount_value'] ?? 0,
                'notes' => $validated['notes'] ?? null,
            ]);

            $createdItems = $this->createOrderItems($order, $validated['items']);
            $this->recalculateOrder($order);
            $this->dispatchKitchenTickets($order, $createdItems, 'order');

            if ($order->table_id) {
                DiningTable::where('id', $order->table_id)->update(['status' => 'occupied']);
            }

            return $order->fresh(['items.modifiers', 'kitchenTickets', 'table']);
        });

        return response()->json(['data' => $order], 201);
    }

    public function show(Order $order): JsonResponse
    {
        return response()->json([
            'data' => $order->load(['items.modifiers', 'kitchenTickets', 'table', 'payments.paymentMethod']),
        ]);
    }

    public function addItems(Request $request, Order $order): JsonResponse
    {
        if (! in_array($order->status, ['open', 'held'], true)) {
            throw ValidationException::withMessages([
                'order' => ['Tidak dapat menambah item pada order yang sudah selesai/dibatalkan.'],
            ]);
        }

        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.product_variant_id' => ['nullable', 'integer', 'exists:product_variants,id'],
            'items.*.modifier_ids' => ['nullable', 'array'],
            'items.*.modifier_ids.*' => ['integer', 'exists:modifiers,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.discount_type' => ['nullable', 'string', 'in:percentage,fixed'],
            'items.*.discount_value' => ['nullable', 'integer', 'min:0'],
            'items.*.notes' => ['nullable', 'string'],
        ]);

        $updatedOrder = DB::transaction(function () use ($order, $validated) {
            $newItems = $this->createOrderItems($order, $validated['items']);
            $this->recalculateOrder($order);
            $this->dispatchKitchenTickets($order, $newItems, 'order');

            return $order->fresh(['items.modifiers', 'kitchenTickets', 'table']);
        });

        return response()->json(['data' => $updatedOrder]);
    }

    public function hold(Request $request, Order $order): JsonResponse
    {
        $validated = $request->validate([
            'action' => ['nullable', 'string', 'in:hold,resume'],
        ]);

        $action = $validated['action'] ?? ($order->status === 'held' ? 'resume' : 'hold');

        if ($action === 'hold') {
            $order->update([
                'status' => 'held',
                'held_at' => now(),
            ]);
        } else {
            $order->update([
                'status' => 'open',
                'held_at' => null,
            ]);
        }

        return response()->json(['data' => $order->fresh(['items.modifiers'])]);
    }

    public function voidItem(Request $request, Order $order): JsonResponse
    {
        $validated = $request->validate([
            'order_item_id' => ['required', 'integer', 'exists:order_items,id'],
            'manager_pin' => ['required', 'string'],
            'reason' => ['required', 'string', 'min:3'],
        ]);

        $authorizer = $this->managerPinService->verifyManagerPin($order->outlet_id, $validated['manager_pin']);
        if (! $authorizer) {
            return response()->json([
                'message' => 'PIN Manager tidak valid. Otorisasi void ditolak.',
            ], 403);
        }

        $orderItem = OrderItem::where('order_id', $order->id)
            ->findOrFail($validated['order_item_id']);

        DB::transaction(function () use ($order, $orderItem, $authorizer, $validated, $request) {
            $orderItem->update([
                'is_voided' => true,
                'void_reason' => $validated['reason'],
                'voided_by' => $authorizer->id,
            ]);

            $this->recalculateOrder($order);

            // Dispatch cancellation ticket to kitchen/bar (KIT-05)
            $this->dispatchKitchenTickets($order, [$orderItem], 'void');

            // Record audit log (USR-04)
            $this->auditLogService->record(
                outletId: $order->outlet_id,
                userId: $request->user()->id,
                authorizedBy: $authorizer->id,
                action: 'order.void_item',
                auditableType: OrderItem::class,
                auditableId: $orderItem->id,
                reason: $validated['reason'],
                payload: [
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'product_name' => $orderItem->product_name,
                    'quantity' => $orderItem->quantity,
                ]
            );
        });

        return response()->json([
            'data' => $order->fresh(['items.modifiers', 'kitchenTickets']),
        ]);
    }

    public function split(Request $request, Order $order): JsonResponse
    {
        $validated = $request->validate([
            'mode' => ['required', 'string', 'in:equal,items'],
            'parts' => ['required_if:mode,equal', 'integer', 'min:2', 'max:20'],
            'splits' => ['required_if:mode,items', 'array'],
        ]);

        $outlet = Outlet::findOrFail($order->outlet_id);

        $splitOrders = DB::transaction(function () use ($order, $validated, $outlet, $request) {
            $createdSplits = [];

            if ($validated['mode'] === 'equal') {
                $parts = (int) $validated['parts'];
                $baseShare = intdiv($order->grand_total, $parts);
                $remainder = $order->grand_total - ($baseShare * $parts);

                for ($i = 1; $i <= $parts; $i++) {
                    $amount = $baseShare + ($i === 1 ? $remainder : 0);
                    $child = Order::create([
                        'outlet_id' => $order->outlet_id,
                        'shift_id' => $order->shift_id,
                        'table_id' => $order->table_id,
                        'parent_order_id' => $order->id,
                        'user_id' => $request->user()->id,
                        'order_number' => $this->orderNumberService->generate($outlet)."-S{$i}",
                        'order_type' => $order->order_type,
                        'customer_name' => ($order->customer_name ?? 'Guest')." (Split {$i}/{$parts})",
                        'guest_count' => 1,
                        'status' => 'open',
                        'gross_subtotal' => $amount,
                        'subtotal' => $amount,
                        'grand_total' => $amount,
                    ]);
                    $createdSplits[] = $child;
                }
            }

            $order->update(['status' => 'split']);

            return $createdSplits;
        });

        return response()->json([
            'parent_order' => $order->fresh(),
            'split_orders' => $splitOrders,
        ], 201);
    }

    /**
     * @param  array<int, array<string, mixed>>  $itemsInput
     * @return array<int, OrderItem>
     */
    protected function createOrderItems(Order $order, array $itemsInput): array
    {
        $created = [];

        foreach ($itemsInput as $itemData) {
            $product = Product::with('prices')->findOrFail($itemData['product_id']);

            if (! $product->is_available) {
                throw ValidationException::withMessages([
                    'items' => ["Produk {$product->name} sedang habis (BR-08)."],
                ]);
            }

            $unitPrice = $product->resolvePriceForOrderType($order->order_type);

            $variant = null;
            $variantAdjustment = 0;
            if (! empty($itemData['product_variant_id'])) {
                $variant = ProductVariant::where('product_id', $product->id)
                    ->findOrFail($itemData['product_variant_id']);
                $variantAdjustment = (int) $variant->price_adjustment;
            }

            $modifierIds = $itemData['modifier_ids'] ?? [];
            $modifiers = ! empty($modifierIds)
                ? Modifier::whereIn('id', $modifierIds)->get()
                : collect();

            $modifiersPrice = (int) $modifiers->sum('price');
            $quantity = (int) $itemData['quantity'];

            $effectiveUnit = $unitPrice + $variantAdjustment + $modifiersPrice;
            $lineGross = $effectiveUnit * $quantity;

            $discountType = $itemData['discount_type'] ?? null;
            $discountValue = (int) ($itemData['discount_value'] ?? 0);
            $discountAmount = 0;

            if ($discountType === 'percentage' && $discountValue > 0) {
                $discountAmount = (int) round($lineGross * ($discountValue / 100));
            } elseif ($discountType === 'fixed' && $discountValue > 0) {
                $discountAmount = $discountValue;
            }
            $discountAmount = min($lineGross, max(0, $discountAmount));

            $orderItem = OrderItem::create([
                'order_id' => $order->id,
                'product_id' => $product->id,
                'product_variant_id' => $variant?->id,
                'product_name' => $product->name,
                'variant_name' => $variant?->name,
                'station' => $product->station,
                'unit_price' => $unitPrice,
                'variant_price_adjustment' => $variantAdjustment,
                'modifiers_price' => $modifiersPrice,
                'quantity' => $quantity,
                'discount_type' => $discountType,
                'discount_value' => $discountValue,
                'discount_amount' => $discountAmount,
                'subtotal' => $lineGross - $discountAmount,
                'notes' => $itemData['notes'] ?? null,
            ]);

            foreach ($modifiers as $mod) {
                $orderItem->modifiers()->create([
                    'modifier_id' => $mod->id,
                    'modifier_name' => $mod->name,
                    'price' => $mod->price,
                ]);
            }

            $created[] = $orderItem->load('modifiers');
        }

        return $created;
    }

    public function recalculateOrder(Order $order): void
    {
        $order->load('items');

        $taxRule = TaxRule::where('outlet_id', $order->outlet_id)
            ->where('is_active', true)
            ->first();

        $itemsPayload = $order->items->map(fn (OrderItem $item) => [
            'unit_price' => $item->unit_price,
            'variant_price_adjustment' => $item->variant_price_adjustment,
            'modifiers_price' => $item->modifiers_price,
            'quantity' => $item->quantity,
            'discount_type' => $item->discount_type,
            'discount_value' => $item->discount_value,
            'is_voided' => $item->is_voided,
        ])->all();

        $orderDiscount = $order->discount_type ? [
            'type' => $order->discount_type,
            'value' => $order->discount_value,
        ] : null;

        $calc = $this->billCalculator->calculate(
            items: $itemsPayload,
            orderDiscount: $orderDiscount,
            taxConfig: [
                'rate' => $taxRule ? $taxRule->rate : 0,
                'inclusive' => $taxRule ? $taxRule->is_inclusive : false,
                'service_charge_rate' => $taxRule ? $taxRule->service_charge_rate : 0,
                'rounding_multiple' => $taxRule ? $taxRule->rounding_multiple : 0,
            ]
        );

        $order->update([
            'gross_subtotal' => $calc['gross_subtotal'],
            'item_discount_total' => $calc['item_discount_total'],
            'subtotal' => $calc['subtotal'],
            'discount_amount' => $calc['order_discount_amount'],
            'service_charge_amount' => $calc['service_charge_amount'],
            'tax_amount' => $calc['tax_amount'],
            'rounding_amount' => $calc['rounding_amount'],
            'grand_total' => $calc['grand_total'],
        ]);
    }

    /**
     * @param  array<int, OrderItem>  $items
     */
    protected function dispatchKitchenTickets(Order $order, array $items, string $type = 'order'): void
    {
        $grouped = collect($items)->groupBy('station');

        foreach ($grouped as $station => $stationItems) {
            KitchenTicket::create([
                'outlet_id' => $order->outlet_id,
                'order_id' => $order->id,
                'ticket_number' => $order->order_number.'-'.strtoupper($station).'-'.now()->format('His'),
                'station' => $station,
                'type' => $type,
                'status' => $type === 'void' ? 'cancelled' : 'new',
                'printed_at' => now(),
                'items_payload' => collect($stationItems)->map(fn (OrderItem $item) => [
                    'order_item_id' => $item->id,
                    'product_name' => $item->product_name,
                    'variant_name' => $item->variant_name,
                    'quantity' => $item->quantity,
                    'notes' => $item->notes,
                    'void_reason' => $item->void_reason,
                    'modifiers' => $item->modifiers->pluck('modifier_name')->all(),
                ])->values()->all(),
            ]);
        }
    }
}
