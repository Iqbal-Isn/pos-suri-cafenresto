<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\Category;
use App\Models\DiningTable;
use App\Models\Modifier;
use App\Models\ModifierGroup;
use App\Models\Outlet;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\TaxRule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ShiftAndOrderFlowTest extends TestCase
{
    use RefreshDatabase;

    protected Outlet $outlet;
    protected User $manager;
    protected User $cashier;
    protected Product $coffee;
    protected Product $pasta;
    protected ProductVariant $icedVariant;
    protected Modifier $extraShot;
    protected DiningTable $table;

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
            'service_charge_rate' => 5,
            'rounding_multiple' => 100,
            'discount_approval_threshold' => 50000,
            'is_active' => true,
        ]);

        foreach (['pos.operate', 'order.void', 'catalog.manage'] as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }

        $managerRole = Role::firstOrCreate(['name' => 'manager', 'guard_name' => 'web']);
        $managerRole->syncPermissions(Permission::all());

        $cashierRole = Role::firstOrCreate(['name' => 'kasir', 'guard_name' => 'web']);
        $cashierRole->syncPermissions(['pos.operate']);

        $this->manager = User::create([
            'outlet_id' => $this->outlet->id,
            'name' => 'Budi Manager',
            'email' => 'manager@suri.test',
            'password' => Hash::make('password'),
            'pin' => Hash::make('998877'),
            'is_active' => true,
        ]);
        $this->manager->assignRole('manager');

        $this->cashier = User::create([
            'outlet_id' => $this->outlet->id,
            'name' => 'Siti Kasir',
            'email' => 'kasir@suri.test',
            'password' => Hash::make('password'),
            'pin' => Hash::make('123456'),
            'is_active' => true,
        ]);
        $this->cashier->assignRole('kasir');

        $cat = Category::create([
            'outlet_id' => $this->outlet->id,
            'name' => 'Menu Utama',
            'slug' => 'menu-utama',
        ]);

        $this->coffee = Product::create([
            'outlet_id' => $this->outlet->id,
            'category_id' => $cat->id,
            'name' => 'Kopi Susu Gula Aren',
            'base_price' => 22000,
            'station' => 'bar',
            'is_available' => true,
        ]);

        $this->icedVariant = ProductVariant::create([
            'product_id' => $this->coffee->id,
            'name' => 'Iced',
            'type' => 'temperature',
            'price_adjustment' => 2000,
            'is_available' => true,
        ]);

        $modGroup = ModifierGroup::create([
            'outlet_id' => $this->outlet->id,
            'name' => 'Add-on',
        ]);

        $this->extraShot = Modifier::create([
            'modifier_group_id' => $modGroup->id,
            'name' => 'Extra Espresso Shot',
            'price' => 6000,
            'is_available' => true,
        ]);

        $this->pasta = Product::create([
            'outlet_id' => $this->outlet->id,
            'category_id' => $cat->id,
            'name' => 'Spaghetti Aglio Olio',
            'base_price' => 45000,
            'station' => 'kitchen',
            'is_available' => true,
        ]);

        $area = Area::create([
            'outlet_id' => $this->outlet->id,
            'name' => 'Main Hall',
        ]);

        $this->table = DiningTable::create([
            'outlet_id' => $this->outlet->id,
            'area_id' => $area->id,
            'name' => 'T-05',
            'capacity' => 4,
            'status' => 'available',
        ]);
    }

    public function test_enforces_open_shift_creates_order_with_kitchen_tickets_open_bill_hold_void_and_split_bill(): void
    {
        // BR-04: Cannot create order before shift is open
        $this->actingAs($this->cashier, 'sanctum')
            ->postJson('/api/v1/orders', [
                'order_type' => 'dine_in',
                'table_id' => $this->table->id,
                'items' => [
                    ['product_id' => $this->coffee->id, 'quantity' => 1],
                ],
            ])
            ->assertStatus(422);

        // Open shift (SHF-01)
        $shiftRes = $this->actingAs($this->cashier, 'sanctum')
            ->postJson('/api/v1/shifts/open', [
                'opening_cash' => 500000,
            ])
            ->assertCreated();

        $shiftId = $shiftRes->json('data.id');

        // Petty cash movement (SHF-02)
        $this->actingAs($this->cashier, 'sanctum')
            ->postJson("/api/v1/shifts/{$shiftId}/cash-movements", [
                'type' => 'out',
                'amount' => 25000,
                'reason' => 'Beli es batu darurat',
            ])
            ->assertCreated();

        // Create Dine-In Order with Bar + Kitchen items
        $orderRes = $this->actingAs($this->cashier, 'sanctum')
            ->postJson('/api/v1/orders', [
                'order_type' => 'dine_in',
                'table_id' => $this->table->id,
                'customer_name' => 'Andi',
                'guest_count' => 2,
                'items' => [
                    [
                        'product_id' => $this->coffee->id,
                        'product_variant_id' => $this->icedVariant->id,
                        'modifier_ids' => [$this->extraShot->id],
                        'quantity' => 2,
                        'notes' => 'Less ice',
                    ],
                    [
                        'product_id' => $this->pasta->id,
                        'quantity' => 1,
                        'notes' => 'Pedas sedang',
                    ],
                ],
            ])
            ->assertCreated();

        $orderId = $orderRes->json('data.id');
        // Coffee: (22000 + 2000 + 6000) * 2 = 60000
        // Pasta: 45000 * 1 = 45000
        // Subtotal = 105000, Service 5% = 5250, Tax 10% of 110250 = 11025 -> 121275 -> rounded 100 = 121300
        $this->assertSame(105000, $orderRes->json('data.subtotal'));
        $this->assertSame(121300, $orderRes->json('data.grand_total'));

        // Table status updated to occupied (TBL-02)
        $this->assertDatabaseHas('tables', [
            'id' => $this->table->id,
            'status' => 'occupied',
        ]);

        // Kitchen tickets dispatched per station: 1 for bar, 1 for kitchen (KIT-01)
        $this->assertDatabaseHas('kitchen_tickets', [
            'order_id' => $orderId,
            'station' => 'bar',
            'type' => 'order',
        ]);
        $this->assertDatabaseHas('kitchen_tickets', [
            'order_id' => $orderId,
            'station' => 'kitchen',
            'type' => 'order',
        ]);

        // Open bill: Add 1 more Pasta (TBL-05)
        $addRes = $this->actingAs($this->cashier, 'sanctum')
            ->postJson("/api/v1/orders/{$orderId}/items", [
                'items' => [
                    [
                        'product_id' => $this->pasta->id,
                        'quantity' => 1,
                    ],
                ],
            ])
            ->assertOk();

        $this->assertSame(150000, $addRes->json('data.subtotal'));

        // Hold and Resume order (TRX-05)
        $this->actingAs($this->cashier, 'sanctum')
            ->postJson("/api/v1/orders/{$orderId}/hold", ['action' => 'hold'])
            ->assertOk()
            ->assertJsonPath('data.status', 'held');

        $this->actingAs($this->cashier, 'sanctum')
            ->postJson("/api/v1/orders/{$orderId}/hold", ['action' => 'resume'])
            ->assertOk()
            ->assertJsonPath('data.status', 'open');

        // Void item fails with wrong manager PIN (TRX-06, BR-05)
        $lastItemId = collect($addRes->json('data.items'))->last()['id'];
        $this->actingAs($this->cashier, 'sanctum')
            ->postJson("/api/v1/orders/{$orderId}/void-item", [
                'order_item_id' => $lastItemId,
                'manager_pin' => '000000',
                'reason' => 'Tamu batal pesan',
            ])
            ->assertStatus(403);

        // Void item succeeds with valid manager PIN
        $voidRes = $this->actingAs($this->cashier, 'sanctum')
            ->postJson("/api/v1/orders/{$orderId}/void-item", [
                'order_item_id' => $lastItemId,
                'manager_pin' => '998877',
                'reason' => 'Tamu batal pesan',
            ])
            ->assertOk();

        $this->assertSame(105000, $voidRes->json('data.subtotal'));
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'order.void_item',
            'auditable_id' => $lastItemId,
            'authorized_by' => $this->manager->id,
        ]);
        $this->assertDatabaseHas('kitchen_tickets', [
            'order_id' => $orderId,
            'type' => 'void',
            'station' => 'kitchen',
        ]);

        // Split bill by equal 2 ways (TBL-04)
        $splitRes = $this->actingAs($this->cashier, 'sanctum')
            ->postJson("/api/v1/orders/{$orderId}/split", [
                'mode' => 'equal',
                'parts' => 2,
            ])
            ->assertCreated();

        $this->assertCount(2, $splitRes->json('split_orders'));
    }
}
