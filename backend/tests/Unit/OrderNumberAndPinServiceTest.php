<?php

namespace Tests\Unit;

use App\Models\Outlet;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\ManagerPinService;
use App\Services\OrderNumberService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class OrderNumberAndPinServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_generates_unique_sequential_order_number_per_outlet_per_day(): void
    {
        $outlet = Outlet::create([
            'code' => 'OUT1',
            'name' => 'Outlet 1',
            'is_active' => true,
        ]);

        $user = User::create([
            'outlet_id' => $outlet->id,
            'name' => 'Kasir',
            'email' => 'kasir@suri.test',
            'password' => Hash::make('secret'),
        ]);

        $service = new OrderNumberService();
        $date = Carbon::parse('2026-11-03 10:00:00');

        $num1 = $service->generate($outlet, $date);
        $this->assertSame('OUT1-20261103-0001', $num1);

        \App\Models\Order::create([
            'outlet_id' => $outlet->id,
            'user_id' => $user->id,
            'order_number' => $num1,
            'order_type' => 'dine_in',
            'status' => 'open',
            'created_at' => $date,
        ]);

        $num2 = $service->generate($outlet, $date);
        $this->assertSame('OUT1-20261103-0002', $num2);
    }

    public function test_verifies_manager_pin_and_records_audit_log(): void
    {
        $outlet = Outlet::create([
            'code' => 'OUT1',
            'name' => 'Outlet 1',
            'is_active' => true,
        ]);

        Role::firstOrCreate(['name' => 'manager', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'kasir', 'guard_name' => 'web']);

        $manager = User::create([
            'outlet_id' => $outlet->id,
            'name' => 'Budi Manager',
            'email' => 'manager@suri.test',
            'password' => Hash::make('secret'),
            'pin' => Hash::make('998877'),
            'is_active' => true,
        ]);
        $manager->assignRole('manager');

        $cashier = User::create([
            'outlet_id' => $outlet->id,
            'name' => 'Siti Kasir',
            'email' => 'kasir@suri.test',
            'password' => Hash::make('secret'),
            'pin' => Hash::make('111111'),
            'is_active' => true,
        ]);
        $cashier->assignRole('kasir');

        $pinService = new ManagerPinService();

        $this->assertNull($pinService->verifyManagerPin($outlet->id, '111111'));
        $verifiedManager = $pinService->verifyManagerPin($outlet->id, '998877');
        $this->assertNotNull($verifiedManager);
        $this->assertSame($manager->id, $verifiedManager->id);

        $auditService = new AuditLogService();
        $log = $auditService->record(
            outletId: $outlet->id,
            userId: $cashier->id,
            authorizedBy: $verifiedManager->id,
            action: 'order.void_item',
            auditableType: 'OrderItem',
            auditableId: 42,
            reason: 'Salah input varian',
            payload: ['qty' => 1]
        );

        $this->assertDatabaseHas('audit_logs', [
            'id' => $log->id,
            'action' => 'order.void_item',
            'authorized_by' => $manager->id,
            'reason' => 'Salah input varian',
        ]);
    }
}
