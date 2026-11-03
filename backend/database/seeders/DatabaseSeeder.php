<?php

namespace Database\Seeders;

use App\Models\Area;
use App\Models\Category;
use App\Models\DiningTable;
use App\Models\Modifier;
use App\Models\ModifierGroup;
use App\Models\Outlet;
use App\Models\PaymentMethod;
use App\Models\Printer;
use App\Models\Product;
use App\Models\ReceiptTemplate;
use App\Models\TaxRule;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Outlet
        $outlet = Outlet::firstOrCreate(
            ['code' => 'OUT1'],
            [
                'name' => 'Suri Cafe & Resto — Cabang Utama',
                'address' => 'Jl. Sudirman No. 88, Jakarta',
                'phone' => '0812-3456-7890',
                'is_active' => true,
            ]
        );

        // 2. Permissions & Roles (PRD Bagian 5)
        $permissions = [
            'catalog.manage',
            'table.manage',
            'pos.operate',
            'order.void',
            'payment.refund',
            'shift.manage',
            'kitchen.operate',
            'report.view',
            'settings.manage',
        ];

        foreach ($permissions as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }

        $ownerRole = Role::firstOrCreate(['name' => 'owner', 'guard_name' => 'web']);
        $ownerRole->syncPermissions($permissions);

        $managerRole = Role::firstOrCreate(['name' => 'manager', 'guard_name' => 'web']);
        $managerRole->syncPermissions($permissions);

        $cashierRole = Role::firstOrCreate(['name' => 'kasir', 'guard_name' => 'web']);
        $cashierRole->syncPermissions(['pos.operate', 'shift.manage']);

        $waiterRole = Role::firstOrCreate(['name' => 'waiter', 'guard_name' => 'web']);
        $waiterRole->syncPermissions(['pos.operate']);

        $kitchenRole = Role::firstOrCreate(['name' => 'dapur', 'guard_name' => 'web']);
        $kitchenRole->syncPermissions(['kitchen.operate']);

        // 3. Default Users with PINs
        $users = [
            [
                'name' => 'Owner Suri',
                'email' => 'owner@suri.test',
                'pin' => '000000',
                'role' => 'owner',
            ],
            [
                'name' => 'Budi Manager',
                'email' => 'manager@suri.test',
                'pin' => '998877',
                'role' => 'manager',
            ],
            [
                'name' => 'Siti Kasir',
                'email' => 'kasir@suri.test',
                'pin' => '123456',
                'role' => 'kasir',
            ],
            [
                'name' => 'Rian Waiter',
                'email' => 'waiter@suri.test',
                'pin' => '223344',
                'role' => 'waiter',
            ],
            [
                'name' => 'Chef Dapur & Bar',
                'email' => 'dapur@suri.test',
                'pin' => '556677',
                'role' => 'dapur',
            ],
        ];

        foreach ($users as $u) {
            $user = User::updateOrCreate(
                ['email' => $u['email']],
                [
                    'outlet_id' => $outlet->id,
                    'name' => $u['name'],
                    'password' => Hash::make('password123'),
                    'pin' => Hash::make($u['pin']),
                    'is_active' => true,
                ]
            );
            $user->syncRoles([$u['role']]);
        }

        // 4. Tax Rule, Receipt Template & Printers
        TaxRule::updateOrCreate(
            ['outlet_id' => $outlet->id, 'name' => 'PB1'],
            [
                'rate' => 10.00,
                'is_inclusive' => false,
                'service_charge_rate' => 5.00,
                'rounding_multiple' => 100,
                'discount_approval_threshold' => 50000,
                'is_active' => true,
            ]
        );

        ReceiptTemplate::updateOrCreate(
            ['outlet_id' => $outlet->id],
            [
                'header_text' => 'Suri Cafe & Resto — Nikmati Kopi & Hidangan Terbaik',
                'footer_text' => 'Terima kasih atas kunjungan Anda!',
                'show_wifi' => true,
                'wifi_ssid' => 'SuriCafe_Guest',
                'wifi_password' => 'kopisuri123',
            ]
        );

        $printers = [
            ['name' => 'Printer Kasir Utama', 'station' => 'cashier', 'connection_type' => 'usb', 'address' => 'USB001', 'paper_width' => 80],
            ['name' => 'Printer Bar Kopi', 'station' => 'bar', 'connection_type' => 'lan', 'address' => '192.168.1.201:9100', 'paper_width' => 80],
            ['name' => 'Printer Kitchen Utama', 'station' => 'kitchen', 'connection_type' => 'lan', 'address' => '192.168.1.202:9100', 'paper_width' => 80],
        ];

        foreach ($printers as $p) {
            Printer::firstOrCreate(
                ['outlet_id' => $outlet->id, 'name' => $p['name']],
                $p
            );
        }

        // 5. Payment Methods (PAY-01)
        $paymentMethods = [
            ['code' => 'cash', 'name' => 'Tunai', 'type' => 'cash'],
            ['code' => 'qris', 'name' => 'QRIS Static/Dynamic', 'type' => 'non_cash'],
            ['code' => 'debit', 'name' => 'Kartu Debit EDC', 'type' => 'non_cash'],
            ['code' => 'credit', 'name' => 'Kartu Kredit EDC', 'type' => 'non_cash'],
            ['code' => 'transfer', 'name' => 'Transfer Bank', 'type' => 'non_cash'],
            ['code' => 'ewallet', 'name' => 'E-Wallet (GoPay/OVO/Dana)', 'type' => 'non_cash'],
        ];

        foreach ($paymentMethods as $pm) {
            PaymentMethod::firstOrCreate(
                ['outlet_id' => $outlet->id, 'code' => $pm['code']],
                ['name' => $pm['name'], 'type' => $pm['type'], 'is_active' => true]
            );
        }

        // 6. Areas & Visual Dining Tables (TBL-01)
        $indoor = Area::firstOrCreate(['outlet_id' => $outlet->id, 'name' => 'Indoor AC'], ['sort_order' => 1]);
        $outdoor = Area::firstOrCreate(['outlet_id' => $outlet->id, 'name' => 'Outdoor Garden'], ['sort_order' => 2]);

        $tables = [
            ['area_id' => $indoor->id, 'name' => 'T-01', 'capacity' => 2, 'pos_x' => 50, 'pos_y' => 50],
            ['area_id' => $indoor->id, 'name' => 'T-02', 'capacity' => 4, 'pos_x' => 200, 'pos_y' => 50],
            ['area_id' => $indoor->id, 'name' => 'T-03', 'capacity' => 4, 'pos_x' => 350, 'pos_y' => 50],
            ['area_id' => $indoor->id, 'name' => 'T-04', 'capacity' => 6, 'pos_x' => 50, 'pos_y' => 200],
            ['area_id' => $outdoor->id, 'name' => 'G-01', 'capacity' => 4, 'pos_x' => 50, 'pos_y' => 50],
            ['area_id' => $outdoor->id, 'name' => 'G-02', 'capacity' => 4, 'pos_x' => 200, 'pos_y' => 50],
        ];

        foreach ($tables as $t) {
            DiningTable::firstOrCreate(
                ['outlet_id' => $outlet->id, 'name' => $t['name']],
                array_merge($t, ['status' => 'available'])
            );
        }

        // 7. Catalog: Categories, Modifier Groups, Products, Variants & Prices
        $catCoffee = Category::firstOrCreate(
            ['outlet_id' => $outlet->id, 'slug' => 'signature-coffee'],
            ['name' => 'Signature Coffee', 'sort_order' => 1, 'is_active' => true]
        );
        $catMain = Category::firstOrCreate(
            ['outlet_id' => $outlet->id, 'slug' => 'main-course'],
            ['name' => 'Main Course', 'sort_order' => 2, 'is_active' => true]
        );
        $catPastry = Category::firstOrCreate(
            ['outlet_id' => $outlet->id, 'slug' => 'pastry-snack'],
            ['name' => 'Pastry & Snack', 'sort_order' => 3, 'is_active' => true]
        );

        $addonGroup = ModifierGroup::firstOrCreate(
            ['outlet_id' => $outlet->id, 'name' => 'Coffee Add-ons'],
            ['is_required' => false, 'min_select' => 0, 'max_select' => 3]
        );

        foreach ([
            ['name' => 'Extra Espresso Shot', 'price' => 6000],
            ['name' => 'Oat Milk Upgrade', 'price' => 8000],
            ['name' => 'Vanilla Syrup', 'price' => 5000],
        ] as $mod) {
            Modifier::firstOrCreate(
                ['modifier_group_id' => $addonGroup->id, 'name' => $mod['name']],
                ['price' => $mod['price'], 'is_available' => true]
            );
        }

        $productsData = [
            [
                'category_id' => $catCoffee->id,
                'sku' => 'COF-001',
                'name' => 'Kopi Susu Suri Gula Aren',
                'base_price' => 24000,
                'station' => 'bar',
                'is_favorite' => true,
                'variants' => [
                    ['name' => 'Regular Iced', 'type' => 'size', 'price_adjustment' => 0],
                    ['name' => 'Large Iced', 'type' => 'size', 'price_adjustment' => 5000],
                ],
                'attach_coffee_addons' => true,
            ],
            [
                'category_id' => $catCoffee->id,
                'sku' => 'COF-002',
                'name' => 'Caramel Macchiato',
                'base_price' => 30000,
                'station' => 'bar',
                'is_favorite' => true,
                'variants' => [
                    ['name' => 'Hot', 'type' => 'temperature', 'price_adjustment' => 0],
                    ['name' => 'Iced', 'type' => 'temperature', 'price_adjustment' => 2000],
                ],
                'attach_coffee_addons' => true,
            ],
            [
                'category_id' => $catMain->id,
                'sku' => 'FOD-001',
                'name' => 'Nasi Goreng Spesial Suri',
                'base_price' => 38000,
                'station' => 'kitchen',
                'is_favorite' => true,
                'variants' => [],
                'attach_coffee_addons' => false,
            ],
            [
                'category_id' => $catMain->id,
                'sku' => 'FOD-002',
                'name' => 'Spaghetti Aglio Olio Tuna',
                'base_price' => 45000,
                'station' => 'kitchen',
                'is_favorite' => false,
                'variants' => [],
                'attach_coffee_addons' => false,
            ],
            [
                'category_id' => $catPastry->id,
                'sku' => 'PAS-001',
                'name' => 'Butter Croissant',
                'base_price' => 22000,
                'station' => 'pastry',
                'is_favorite' => true,
                'variants' => [],
                'attach_coffee_addons' => false,
            ],
        ];

        foreach ($productsData as $pData) {
            $product = Product::firstOrCreate(
                ['outlet_id' => $outlet->id, 'sku' => $pData['sku']],
                [
                    'category_id' => $pData['category_id'],
                    'name' => $pData['name'],
                    'base_price' => $pData['base_price'],
                    'station' => $pData['station'],
                    'is_available' => true,
                    'is_favorite' => $pData['is_favorite'],
                ]
            );

            foreach ($pData['variants'] as $v) {
                $product->variants()->firstOrCreate(
                    ['name' => $v['name']],
                    ['type' => $v['type'], 'price_adjustment' => $v['price_adjustment'], 'is_available' => true]
                );
            }

            if ($pData['attach_coffee_addons']) {
                $product->modifierGroups()->syncWithoutDetaching([$addonGroup->id]);
            }
        }
    }
}
