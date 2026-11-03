<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Outlet;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\TaxRule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PaymentSyncAndReportTest extends TestCase
{
    use RefreshDatabase;

    protected Outlet $outlet;
    protected User $manager;
    protected User $cashier;
    protected Product $coffee;
    protected PaymentMethod $cashMethod;
    protected PaymentMethod $qrisMethod;

    protected function setUp(): void
    {
        parent::setUp();

        $this->outlet = Outlet::create([
            'code' => 'OUT1',
            'name' => 'Suri Cafe & Resto Pusat',
            'is_active' => true,
        ]);

        TaxRule::create([
            'outlet_id' => $this->outlet->id,
            'name' => 'PB1',
            'rate' => 10,
            'is_inclusive' => false,
            'service_charge_rate' => 0,
            'rounding_multiple' => 0,
            'discount_approval_threshold' => 50000,
            'is_active' => true,
        ]);

        foreach (['pos.operate', 'report.view', 'settings.manage'] as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }

        $managerRole = Role::firstOrCreate(['name' => 'manager', 'guard_name' => 'web']);
        $managerRole->syncPermissions(Permission::all());

        $cashierRole = Role::firstOrCreate(['name' => 'kasir', 'guard_name' => 'web']);
        $cashierRole->syncPermissions(['pos.operate']);

        $this->manager = User::create([
            'outlet_id' => $this->outlet->id,
            'name' => 'Manager',
            'email' => 'manager@suri.test',
            'password' => Hash::make('password'),
            'pin' => Hash::make('998877'),
            'is_active' => true,
        ]);
        $this->manager->assignRole('manager');

        $this->cashier = User::create([
            'outlet_id' => $this->outlet->id,
            'name' => 'Kasir',
            'email' => 'kasir@suri.test',
            'password' => Hash::make('password'),
            'pin' => Hash::make('123456'),
            'is_active' => true,
        ]);
        $this->cashier->assignRole('kasir');

        $cat = Category::create([
            'outlet_id' => $this->outlet->id,
            'name' => 'Coffee',
            'slug' => 'coffee',
        ]);

        $this->coffee = Product::create([
            'outlet_id' => $this->outlet->id,
            'category_id' => $cat->id,
            'name' => 'Americano',
            'base_price' => 20000,
            'station' => 'bar',
            'is_available' => true,
        ]);

        $this->cashMethod = PaymentMethod::create([
            'outlet_id' => $this->outlet->id,
            'code' => 'cash',
            'name' => 'Tunai',
            'type' => 'cash',
            'is_active' => true,
        ]);

        $this->qrisMethod = PaymentMethod::create([
            'outlet_id' => $this->outlet->id,
            'code' => 'qris',
            'name' => 'QRIS',
            'type' => 'non_cash',
            'is_active' => true,
        ]);
    }

    public function test_split_payment_refund_shift_reconciliation_idempotent_offline_sync_and_reports(): void
    {
        // Open shift with 300,000 modal awal
        $shiftRes = $this->actingAs($this->cashier, 'sanctum')
            ->postJson('/api/v1/shifts/open', ['opening_cash' => 300000])
            ->assertCreated();
        $shiftId = $shiftRes->json('data.id');

        // Create order: 2 Americano = 40,000 + 10% tax = 44,000
        $orderRes = $this->actingAs($this->cashier, 'sanctum')
            ->postJson('/api/v1/orders', [
                'order_type' => 'take_away',
                'items' => [
                    ['product_id' => $this->coffee->id, 'quantity' => 2],
                ],
            ])
            ->assertCreated();
        $orderId = $orderRes->json('data.id');
        $this->assertSame(44000, $orderRes->json('data.grand_total'));

        // Split payment: 20,000 via QRIS + 24,000 via Cash (tendered 50,000 -> change 26,000)
        $payRes = $this->actingAs($this->cashier, 'sanctum')
            ->postJson("/api/v1/orders/{$orderId}/payments", [
                'payments' => [
                    [
                        'payment_method_id' => $this->qrisMethod->id,
                        'amount' => 20000,
                        'tendered_amount' => 20000,
                        'reference_number' => 'QRIS-001',
                    ],
                    [
                        'payment_method_id' => $this->cashMethod->id,
                        'amount' => 24000,
                        'tendered_amount' => 50000,
                    ],
                ],
            ])
            ->assertCreated();

        $this->assertSame('paid', $payRes->json('order.status'));
        $this->assertSame(26000, $payRes->json('order.change_amount'));
        $this->assertNotEmpty($payRes->json('receipt'));

        // Idempotent offline sync (POST /api/v1/sync/batch)
        $offlineUuid = 'offline-order-uuid-001';
        $offlinePaymentUuid = 'offline-payment-uuid-001';
        $syncPayload = [
            'orders' => [
                [
                    'client_uuid' => $offlineUuid,
                    'order_type' => 'take_away',
                    'customer_name' => 'Offline Guest',
                    'items' => [
                        ['product_id' => $this->coffee->id, 'quantity' => 1],
                    ],
                    'payments' => [
                        [
                            'client_uuid' => $offlinePaymentUuid,
                            'payment_method_id' => $this->cashMethod->id,
                            'amount' => 22000,
                            'tendered_amount' => 25000,
                        ],
                    ],
                ],
            ],
        ];

        // First sync call
        $syncRes1 = $this->actingAs($this->cashier, 'sanctum')
            ->postJson('/api/v1/sync/batch', $syncPayload)
            ->assertOk();
        $this->assertSame(1, $syncRes1->json('synced_count'));

        // Second sync call with exact same UUIDs must be idempotent (no duplicate order!)
        $syncRes2 = $this->actingAs($this->cashier, 'sanctum')
            ->postJson('/api/v1/sync/batch', $syncPayload)
            ->assertOk();
        $this->assertSame(2, \App\Models\Order::count());

        // Refund QRIS payment with Manager PIN
        $qrisPaymentId = collect($payRes->json('order.payments'))
            ->firstWhere('payment_method_id', $this->qrisMethod->id)['id'];

        $this->actingAs($this->cashier, 'sanctum')
            ->postJson("/api/v1/payments/{$qrisPaymentId}/refund", [
                'amount' => 20000,
                'reason' => 'Komplain pelanggan',
                'manager_pin' => '998877',
            ])
            ->assertCreated();

        // Close shift (SHF-03)
        // Expected cash = 300,000 (opening) + 24,000 (order 1 cash) + 22,000 (offline order cash) = 346,000
        // Actual cash counted = 345,000 -> discrepancy = -1,000
        $closeRes = $this->actingAs($this->cashier, 'sanctum')
            ->postJson('/api/v1/shifts/close', [
                'shift_id' => $shiftId,
                'actual_cash' => 345000,
                'notes' => 'Selisih seribu rupiah',
            ])
            ->assertOk();

        $this->assertSame(346000, $closeRes->json('data.expected_cash'));
        $this->assertSame(-1000, $closeRes->json('data.discrepancy'));

        // Reports (RPT-01..04, RPT-09)
        $this->actingAs($this->manager, 'sanctum')
            ->getJson('/api/v1/reports/sales')
            ->assertOk()
            ->assertJsonStructure(['summary' => ['total_orders', 'gross_sales', 'net_sales', 'total_refunds']]);

        $this->actingAs($this->manager, 'sanctum')
            ->getJson('/api/v1/reports/products')
            ->assertOk();

        $this->actingAs($this->manager, 'sanctum')
            ->getJson('/api/v1/reports/shifts')
            ->assertOk();

        $this->actingAs($this->manager, 'sanctum')
            ->getJson('/api/v1/reports/voids')
            ->assertOk();

        $csvRes = $this->actingAs($this->manager, 'sanctum')
            ->get('/api/v1/reports/sales?export=csv');
        $csvRes->assertOk();
        $this->assertStringContainsString('text/csv', $csvRes->headers->get('Content-Type'));
    }
}
