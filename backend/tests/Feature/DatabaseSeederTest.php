<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\DiningTable;
use App\Models\Outlet;
use App\Models\PaymentMethod;
use App\Models\Printer;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_seeder_populates_complete_pos_initial_data_and_settings_endpoints_work(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(1, Outlet::count());
        $this->assertSame(5, Role::count());
        $this->assertGreaterThanOrEqual(5, User::count());
        $this->assertGreaterThanOrEqual(5, PaymentMethod::count());
        $this->assertGreaterThanOrEqual(3, Printer::count());
        $this->assertGreaterThanOrEqual(6, DiningTable::count());
        $this->assertGreaterThanOrEqual(3, Category::count());
        $this->assertGreaterThanOrEqual(5, Product::count());

        $owner = User::where('email', 'owner@suri.test')->firstOrFail();

        $this->actingAs($owner, 'sanctum')
            ->getJson('/api/v1/settings')
            ->assertOk()
            ->assertJsonPath('outlet.code', 'OUT1')
            ->assertJsonPath('tax_rule.name', 'PB1');

        $this->actingAs($owner, 'sanctum')
            ->putJson('/api/v1/settings', [
                'tax_rate' => 11,
                'service_charge_rate' => 5,
                'header_text' => 'Suri Cafe & Resto - Cabang Utama',
            ])
            ->assertOk()
            ->assertJsonPath('tax_rule.rate', 11);

        $this->actingAs($owner, 'sanctum')
            ->getJson('/api/v1/printers')
            ->assertOk();
    }
}
