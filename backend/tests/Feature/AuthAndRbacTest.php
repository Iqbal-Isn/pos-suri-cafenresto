<?php

namespace Tests\Feature;

use App\Models\Outlet;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AuthAndRbacTest extends TestCase
{
    use RefreshDatabase;

    protected Outlet $outlet;
    protected User $manager;
    protected User $cashier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->outlet = Outlet::create([
            'code' => 'OUT1',
            'name' => 'Suri Cafe & Resto Pusat',
            'address' => 'Jl. Merdeka No. 10',
            'phone' => '081234567890',
            'is_active' => true,
        ]);

        $manageCatalog = Permission::firstOrCreate(['name' => 'catalog.manage', 'guard_name' => 'web']);
        $posOperate = Permission::firstOrCreate(['name' => 'pos.operate', 'guard_name' => 'web']);

        $managerRole = Role::firstOrCreate(['name' => 'manager', 'guard_name' => 'web']);
        $managerRole->syncPermissions([$manageCatalog, $posOperate]);

        $cashierRole = Role::firstOrCreate(['name' => 'kasir', 'guard_name' => 'web']);
        $cashierRole->syncPermissions([$posOperate]);

        $this->manager = User::create([
            'outlet_id' => $this->outlet->id,
            'name' => 'Budi Manager',
            'email' => 'manager@suri.test',
            'password' => Hash::make('password123'),
            'pin' => Hash::make('112233'),
            'is_active' => true,
        ]);
        $this->manager->assignRole('manager');

        $this->cashier = User::create([
            'outlet_id' => $this->outlet->id,
            'name' => 'Siti Kasir',
            'email' => 'kasir@suri.test',
            'password' => Hash::make('password123'),
            'pin' => Hash::make('123456'),
            'is_active' => true,
        ]);
        $this->cashier->assignRole('kasir');
    }

    public function test_user_can_login_with_email_and_password_and_fetch_profile(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'manager@suri.test',
            'password' => 'password123',
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'token',
                'user' => ['id', 'name', 'email', 'outlet_id', 'roles', 'permissions'],
            ]);

        $token = $response->json('token');

        $meResponse = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/me');

        $meResponse->assertOk()
            ->assertJsonPath('user.email', 'manager@suri.test')
            ->assertJsonPath('user.roles.0', 'manager');
    }

    public function test_cashier_can_fast_login_using_pin(): void
    {
        $response = $this->postJson('/api/v1/auth/pin-login', [
            'outlet_id' => $this->outlet->id,
            'pin' => '123456',
        ]);

        $response->assertOk()
            ->assertJsonPath('user.name', 'Siti Kasir')
            ->assertJsonPath('user.roles.0', 'kasir');
    }

    public function test_cashier_is_forbidden_from_managing_catalog_while_manager_is_allowed(): void
    {
        $this->actingAs($this->cashier, 'sanctum')
            ->postJson('/api/v1/categories', [
                'name' => 'Coffee',
            ])
            ->assertForbidden();

        $this->actingAs($this->manager, 'sanctum')
            ->postJson('/api/v1/categories', [
                'name' => 'Coffee',
            ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Coffee');
    }
}
