<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\Category;
use App\Models\DiningTable;
use App\Models\Modifier;
use App\Models\ModifierGroup;
use App\Models\Outlet;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CatalogAndTableTest extends TestCase
{
    use RefreshDatabase;

    protected Outlet $outlet;
    protected User $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->outlet = Outlet::create([
            'code' => 'OUT1',
            'name' => 'Suri Cafe & Resto Pusat',
            'is_active' => true,
        ]);

        foreach (['catalog.manage', 'table.manage', 'pos.operate'] as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }

        $role = Role::firstOrCreate(['name' => 'manager', 'guard_name' => 'web']);
        $role->syncPermissions(Permission::all());

        $this->manager = User::create([
            'outlet_id' => $this->outlet->id,
            'name' => 'Manager',
            'email' => 'manager@suri.test',
            'password' => Hash::make('password'),
            'pin' => Hash::make('123456'),
            'is_active' => true,
        ]);
        $this->manager->assignRole('manager');
    }

    public function test_can_create_category_subcategory_product_with_variants_modifiers_order_type_prices_and_toggle_availability(): void
    {
        $catRes = $this->actingAs($this->manager, 'sanctum')
            ->postJson('/api/v1/categories', [
                'name' => 'Minuman',
                'sort_order' => 1,
            ])
            ->assertCreated();

        $parentId = $catRes->json('data.id');

        $subCatRes = $this->actingAs($this->manager, 'sanctum')
            ->postJson('/api/v1/categories', [
                'parent_id' => $parentId,
                'name' => 'Espresso Based',
                'sort_order' => 1,
            ])
            ->assertCreated();

        $subCategoryId = $subCatRes->json('data.id');

        $modGroup = ModifierGroup::create([
            'outlet_id' => $this->outlet->id,
            'name' => 'Extra Topping',
            'is_required' => false,
            'min_select' => 0,
            'max_select' => 2,
        ]);

        Modifier::create([
            'modifier_group_id' => $modGroup->id,
            'name' => 'Extra Shot',
            'price' => 6000,
            'is_available' => true,
        ]);

        $prodRes = $this->actingAs($this->manager, 'sanctum')
            ->postJson('/api/v1/products', [
                'category_id' => $subCategoryId,
                'sku' => 'COF-001',
                'name' => 'Caramel Macchiato',
                'base_price' => 28000,
                'station' => 'bar',
                'is_available' => true,
                'is_favorite' => true,
                'variants' => [
                    ['name' => 'Hot', 'type' => 'temperature', 'price_adjustment' => 0],
                    ['name' => 'Iced', 'type' => 'temperature', 'price_adjustment' => 2000],
                ],
                'prices' => [
                    ['order_type' => 'dine_in', 'price' => 28000],
                    ['order_type' => 'take_away', 'price' => 29000],
                    ['order_type' => 'delivery', 'price' => 32000],
                ],
                'modifier_group_ids' => [$modGroup->id],
            ])
            ->assertCreated();

        $productId = $prodRes->json('data.id');
        $this->assertCount(2, $prodRes->json('data.variants'));
        $this->assertCount(3, $prodRes->json('data.prices'));

        $this->actingAs($this->manager, 'sanctum')
            ->patchJson("/api/v1/products/{$productId}/availability", [
                'is_available' => false,
            ])
            ->assertOk()
            ->assertJsonPath('data.is_available', false);
    }

    public function test_can_manage_tables_move_and_merge_tables(): void
    {
        $area = Area::create([
            'outlet_id' => $this->outlet->id,
            'name' => 'Indoor AC',
            'sort_order' => 1,
        ]);

        $t1Res = $this->actingAs($this->manager, 'sanctum')
            ->postJson('/api/v1/tables', [
                'area_id' => $area->id,
                'name' => 'T-01',
                'capacity' => 4,
                'pos_x' => 100,
                'pos_y' => 150,
            ])
            ->assertCreated();

        $t2Res = $this->actingAs($this->manager, 'sanctum')
            ->postJson('/api/v1/tables', [
                'area_id' => $area->id,
                'name' => 'T-02',
                'capacity' => 4,
                'pos_x' => 250,
                'pos_y' => 150,
            ])
            ->assertCreated();

        $t3Res = $this->actingAs($this->manager, 'sanctum')
            ->postJson('/api/v1/tables', [
                'area_id' => $area->id,
                'name' => 'T-03',
                'capacity' => 6,
                'pos_x' => 400,
                'pos_y' => 150,
            ])
            ->assertCreated();

        $t1Id = $t1Res->json('data.id');
        $t2Id = $t2Res->json('data.id');
        $t3Id = $t3Res->json('data.id');

        DiningTable::where('id', $t1Id)->update(['status' => 'occupied']);

        // Move table T-01 -> T-02
        $this->actingAs($this->manager, 'sanctum')
            ->postJson("/api/v1/tables/{$t1Id}/move", [
                'target_table_id' => $t2Id,
            ])
            ->assertOk()
            ->assertJsonPath('target_table.status', 'occupied')
            ->assertJsonPath('source_table.status', 'available');

        // Merge T-03 into T-02
        $this->actingAs($this->manager, 'sanctum')
            ->postJson('/api/v1/tables/merge', [
                'primary_table_id' => $t2Id,
                'secondary_table_ids' => [$t3Id],
            ])
            ->assertOk()
            ->assertJsonPath('primary_table.id', $t2Id);

        $this->assertDatabaseHas('tables', [
            'id' => $t3Id,
            'merged_into_table_id' => $t2Id,
            'status' => 'occupied',
        ]);
    }
}
