<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('purchase_invoices', function (Blueprint $table): void {
            $table->id();
            $table->string('invoice_number', 30)->unique();
            $table->string('reference_type', 30)->default('purchase_order');
            $table->string('reference_number', 40)->nullable();
            $table->foreignId('live_chicken_purchase_order_id')
                ->nullable()
                ->constrained('live_chicken_purchase_orders')
                ->nullOnDelete();
            $table->foreignId('goods_receipt_id')
                ->nullable()
                ->constrained('goods_receipts')
                ->nullOnDelete();
            $table->foreignId('supplier_id')
                ->constrained('suppliers')
                ->restrictOnDelete();
            $table->foreignId('destination_warehouse_id')
                ->constrained('warehouses')
                ->restrictOnDelete();
            $table->foreignId('cash_account_id')
                ->nullable()
                ->constrained('chart_of_accounts')
                ->nullOnDelete();
            $table->string('status', 30)->default('draft');
            $table->string('payment_status', 30)->default('unpaid');
            $table->date('invoice_date');
            $table->date('due_date')->nullable();
            $table->string('tax_invoice_number', 40)->nullable();
            $table->string('payment_term', 20)->default('cod');
            $table->string('payment_term_description', 120)->nullable();
            $table->boolean('is_tax_inclusive')->default(false);
            $table->string('tax_dpp_type', 20)->default('100');
            $table->decimal('tax_rate', 5, 2)->default(11.00);
            $table->string('global_discount_type', 20)->default('amount');
            $table->decimal('global_discount_value', 18, 2)->default(0);
            $table->unsignedInteger('line_item_total')->default(0);
            $table->decimal('total_quantity_ea', 18, 3)->default(0);
            $table->decimal('total_weight_kg', 18, 3)->default(0);
            $table->decimal('subtotal', 18, 2)->default(0);
            $table->decimal('discount_total', 18, 2)->default(0);
            $table->decimal('tax_total', 18, 2)->default(0);
            $table->decimal('additional_cost_total', 18, 2)->default(0);
            $table->decimal('grand_total', 18, 2)->default(0);
            $table->decimal('paid_total', 18, 2)->default(0);
            $table->decimal('balance_due', 18, 2)->default(0);
            $table->json('additional_costs')->nullable();
            $table->json('attachments')->nullable();
            $table->text('fob_destination')->nullable();
            $table->text('fob_shipping_point')->nullable();
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('purchase_invoice_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('purchase_invoice_id')
                ->constrained('purchase_invoices')
                ->cascadeOnDelete();
            $table->foreignId('goods_receipt_item_id')
                ->nullable()
                ->constrained('goods_receipt_items')
                ->nullOnDelete();
            $table->foreignId('product_id')
                ->nullable()
                ->constrained('products')
                ->nullOnDelete();
            $table->foreignId('warehouse_id')
                ->nullable()
                ->constrained('warehouses')
                ->nullOnDelete();
            $table->unsignedInteger('line_number')->default(0);
            $table->string('item_code', 30)->nullable();
            $table->string('item_name', 180);
            $table->string('unit', 20)->default('kg');
            $table->decimal('quantity', 18, 3)->default(0);
            $table->decimal('weight_kg', 18, 3)->default(0);
            $table->decimal('unit_price', 18, 2)->default(0);
            $table->string('discount_type', 20)->default('amount');
            $table->decimal('discount_value', 18, 2)->default(0);
            $table->boolean('apply_tax')->default(false);
            $table->decimal('tax_rate', 5, 2)->default(11.00);
            $table->decimal('subtotal', 18, 2)->default(0);
            $table->decimal('discount_total', 18, 2)->default(0);
            $table->decimal('tax_total', 18, 2)->default(0);
            $table->decimal('total', 18, 2)->default(0);
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('purchase_invoice_payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('purchase_invoice_id')
                ->constrained('purchase_invoices')
                ->cascadeOnDelete();
            $table->foreignId('account_id')
                ->nullable()
                ->constrained('chart_of_accounts')
                ->nullOnDelete();
            $table->string('payment_type', 30)->default('down_payment');
            $table->string('payment_method', 40)->nullable();
            $table->string('reference_number', 60)->nullable();
            $table->date('paid_at')->nullable();
            $table->decimal('amount', 18, 2)->default(0);
            $table->boolean('is_manual')->default(true);
            $table->json('attachments')->nullable();
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_invoice_payments');
        Schema::dropIfExists('purchase_invoice_items');
        Schema::dropIfExists('purchase_invoices');
    }
};
