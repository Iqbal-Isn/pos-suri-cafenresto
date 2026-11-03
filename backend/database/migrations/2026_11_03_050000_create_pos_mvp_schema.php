<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Settings & Hardware (SET)
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('outlet_id')->constrained('outlets')->cascadeOnDelete();
            $table->string('key');
            $table->json('value')->nullable();
            $table->timestamps();
            $table->unique(['outlet_id', 'key']);
        });

        Schema::create('tax_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('outlet_id')->constrained('outlets')->cascadeOnDelete();
            $table->string('name')->default('PB1');
            $table->decimal('rate', 5, 2)->default(10.00);
            $table->boolean('is_inclusive')->default(false);
            $table->decimal('service_charge_rate', 5, 2)->default(5.00);
            $table->integer('rounding_multiple')->default(100);
            $table->bigInteger('discount_approval_threshold')->default(50000);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('printers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('outlet_id')->constrained('outlets')->cascadeOnDelete();
            $table->string('name');
            $table->string('station', 32)->default('cashier'); // cashier, bar, kitchen, pastry
            $table->string('connection_type', 32)->default('lan'); // usb, lan, bluetooth, agent
            $table->string('address')->nullable();
            $table->integer('paper_width')->default(80);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('receipt_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('outlet_id')->constrained('outlets')->cascadeOnDelete();
            $table->string('header_text')->nullable();
            $table->string('footer_text')->nullable();
            $table->string('logo_url')->nullable();
            $table->boolean('show_wifi')->default(false);
            $table->string('wifi_ssid')->nullable();
            $table->string('wifi_password')->nullable();
            $table->timestamps();
        });

        // Catalog & Menu (MNU)
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('outlet_id')->constrained('outlets')->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('outlet_id')->constrained('outlets')->cascadeOnDelete();
            $table->foreignId('category_id')->constrained('categories')->cascadeOnDelete();
            $table->string('sku', 64)->nullable();
            $table->string('name');
            $table->text('description')->nullable();
            $table->bigInteger('base_price'); // Integer IDR (BR-01)
            $table->string('station', 32)->default('kitchen'); // bar, kitchen, pastry (MNU-05)
            $table->boolean('is_available')->default(true); // MNU-04
            $table->boolean('is_favorite')->default(false); // TRX-01
            $table->string('image_url')->nullable();
            $table->timestamps();
        });

        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('name');
            $table->string('type', 32)->default('size'); // size, temperature
            $table->bigInteger('price_adjustment')->default(0);
            $table->boolean('is_available')->default(true);
            $table->timestamps();
        });

        Schema::create('modifier_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('outlet_id')->constrained('outlets')->cascadeOnDelete();
            $table->string('name');
            $table->boolean('is_required')->default(false);
            $table->integer('min_select')->default(0);
            $table->integer('max_select')->default(5);
            $table->timestamps();
        });

        Schema::create('modifiers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('modifier_group_id')->constrained('modifier_groups')->cascadeOnDelete();
            $table->string('name');
            $table->bigInteger('price')->default(0);
            $table->boolean('is_available')->default(true);
            $table->timestamps();
        });

        Schema::create('product_modifier_group', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('modifier_group_id')->constrained('modifier_groups')->cascadeOnDelete();
            $table->unique(['product_id', 'modifier_group_id']);
        });

        Schema::create('product_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('order_type', 32); // dine_in, take_away, delivery, pre_order
            $table->bigInteger('price');
            $table->timestamps();
            $table->unique(['product_id', 'order_type']);
        });

        // Tables & Dine-in (TBL)
        Schema::create('areas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('outlet_id')->constrained('outlets')->cascadeOnDelete();
            $table->string('name');
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('tables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('outlet_id')->constrained('outlets')->cascadeOnDelete();
            $table->foreignId('area_id')->constrained('areas')->cascadeOnDelete();
            $table->string('name');
            $table->integer('capacity')->default(4);
            $table->integer('pos_x')->default(0);
            $table->integer('pos_y')->default(0);
            $table->string('status', 32)->default('available'); // available, occupied, bill_requested, cleaning
            $table->foreignId('merged_into_table_id')->nullable()->constrained('tables')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('table_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('outlet_id')->constrained('outlets')->cascadeOnDelete();
            $table->foreignId('table_id')->constrained('tables')->cascadeOnDelete();
            $table->foreignId('opened_by')->constrained('users')->cascadeOnDelete();
            $table->integer('guest_count')->default(1);
            $table->timestamp('opened_at');
            $table->timestamp('closed_at')->nullable();
            $table->string('status', 32)->default('open'); // open, closed
            $table->timestamps();
        });

        // Shifts & Cash (SHF)
        Schema::create('shifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('outlet_id')->constrained('outlets')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('opened_at');
            $table->timestamp('closed_at')->nullable();
            $table->bigInteger('opening_cash')->default(0);
            $table->bigInteger('expected_cash')->default(0);
            $table->bigInteger('actual_cash')->nullable();
            $table->bigInteger('discrepancy')->nullable();
            $table->text('notes')->nullable();
            $table->string('status', 32)->default('open'); // open, closed
            $table->timestamps();
        });

        Schema::create('cash_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('outlet_id')->constrained('outlets')->cascadeOnDelete();
            $table->foreignId('shift_id')->constrained('shifts')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('type', 16); // in, out
            $table->bigInteger('amount');
            $table->string('reason');
            $table->timestamps();
        });

        // Vouchers
        Schema::create('vouchers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('outlet_id')->constrained('outlets')->cascadeOnDelete();
            $table->string('code', 64)->unique();
            $table->string('type', 32)->default('fixed'); // percentage, fixed
            $table->bigInteger('value');
            $table->bigInteger('min_purchase')->default(0);
            $table->bigInteger('max_discount')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });

        // Orders & Kitchen (TRX & KIT)
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('outlet_id')->constrained('outlets')->cascadeOnDelete();
            $table->foreignId('shift_id')->nullable()->constrained('shifts')->nullOnDelete();
            $table->foreignId('table_id')->nullable()->constrained('tables')->nullOnDelete();
            $table->foreignId('parent_order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('client_uuid', 100)->nullable()->unique(); // BR-09 idempotent offline sync
            $table->string('order_number', 64)->unique(); // BR-02
            $table->string('order_type', 32)->default('dine_in'); // dine_in, take_away, delivery, pre_order
            $table->string('customer_name')->nullable();
            $table->integer('guest_count')->default(1);
            $table->string('status', 32)->default('open'); // open, held, paid, voided, refunded, split
            $table->bigInteger('gross_subtotal')->default(0);
            $table->bigInteger('item_discount_total')->default(0);
            $table->bigInteger('subtotal')->default(0);
            $table->string('discount_type', 32)->nullable();
            $table->bigInteger('discount_value')->default(0);
            $table->bigInteger('discount_amount')->default(0);
            $table->string('voucher_code', 64)->nullable();
            $table->bigInteger('service_charge_amount')->default(0);
            $table->bigInteger('tax_amount')->default(0);
            $table->bigInteger('rounding_amount')->default(0);
            $table->bigInteger('grand_total')->default(0);
            $table->bigInteger('paid_amount')->default(0);
            $table->bigInteger('change_amount')->default(0);
            $table->text('notes')->nullable();
            $table->timestamp('held_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->text('void_reason')->nullable();
            $table->timestamps();
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            $table->string('product_name');
            $table->string('variant_name')->nullable();
            $table->string('station', 32)->default('kitchen');
            $table->bigInteger('unit_price');
            $table->bigInteger('variant_price_adjustment')->default(0);
            $table->bigInteger('modifiers_price')->default(0);
            $table->integer('quantity')->default(1);
            $table->string('discount_type', 32)->nullable();
            $table->bigInteger('discount_value')->default(0);
            $table->bigInteger('discount_amount')->default(0);
            $table->bigInteger('subtotal');
            $table->text('notes')->nullable();
            $table->boolean('is_voided')->default(false);
            $table->text('void_reason')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('order_item_modifiers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_item_id')->constrained('order_items')->cascadeOnDelete();
            $table->foreignId('modifier_id')->nullable()->constrained('modifiers')->nullOnDelete();
            $table->string('modifier_name');
            $table->bigInteger('price')->default(0);
            $table->timestamps();
        });

        Schema::create('order_discounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->string('name');
            $table->string('type', 32); // percentage, fixed, voucher
            $table->bigInteger('value');
            $table->bigInteger('amount');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('kitchen_tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('outlet_id')->constrained('outlets')->cascadeOnDelete();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->string('ticket_number', 64);
            $table->string('station', 32); // bar, kitchen, pastry
            $table->string('type', 32)->default('order'); // order, void
            $table->string('status', 32)->default('new'); // new, processing, ready, served, cancelled
            $table->json('items_payload');
            $table->timestamp('printed_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        // Payments (PAY)
        Schema::create('payment_methods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('outlet_id')->constrained('outlets')->cascadeOnDelete();
            $table->string('code', 32); // cash, qris, debit, credit, transfer, ewallet
            $table->string('name');
            $table->string('type', 32)->default('non_cash'); // cash, non_cash
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('outlet_id')->constrained('outlets')->cascadeOnDelete();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('shift_id')->nullable()->constrained('shifts')->nullOnDelete();
            $table->foreignId('payment_method_id')->constrained('payment_methods')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('client_uuid', 100)->nullable()->unique(); // BR-09 idempotent offline sync
            $table->bigInteger('amount');
            $table->bigInteger('tendered_amount');
            $table->bigInteger('change_amount')->default(0);
            $table->string('reference_number')->nullable();
            $table->string('status', 32)->default('completed'); // completed, refunded
            $table->timestamp('paid_at');
            $table->timestamps();
        });

        Schema::create('refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('outlet_id')->constrained('outlets')->cascadeOnDelete();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('payment_id')->constrained('payments')->cascadeOnDelete();
            $table->foreignId('shift_id')->nullable()->constrained('shifts')->nullOnDelete();
            $table->foreignId('requested_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('approved_by')->constrained('users')->cascadeOnDelete();
            $table->bigInteger('amount');
            $table->text('reason');
            $table->timestamps();
        });

        Schema::create('voucher_redemptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('voucher_id')->constrained('vouchers')->cascadeOnDelete();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->bigInteger('discount_amount');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('voucher_redemptions');
        Schema::dropIfExists('refunds');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('payment_methods');
        Schema::dropIfExists('kitchen_tickets');
        Schema::dropIfExists('order_discounts');
        Schema::dropIfExists('order_item_modifiers');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('vouchers');
        Schema::dropIfExists('cash_movements');
        Schema::dropIfExists('shifts');
        Schema::dropIfExists('table_sessions');
        Schema::dropIfExists('tables');
        Schema::dropIfExists('areas');
        Schema::dropIfExists('product_prices');
        Schema::dropIfExists('product_modifier_group');
        Schema::dropIfExists('modifiers');
        Schema::dropIfExists('modifier_groups');
        Schema::dropIfExists('product_variants');
        Schema::dropIfExists('products');
        Schema::dropIfExists('categories');
        Schema::dropIfExists('receipt_templates');
        Schema::dropIfExists('printers');
        Schema::dropIfExists('tax_rules');
        Schema::dropIfExists('settings');
    }
};
