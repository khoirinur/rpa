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
        Schema::create('live_chicken_purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->string('po_number', 30)->unique();
            $table->foreignId('supplier_id')
                ->constrained('suppliers')
                ->restrictOnDelete();
            $table->foreignId('destination_warehouse_id')
                ->constrained('warehouses')
                ->restrictOnDelete();
            $table->text('shipping_address')->nullable();
            $table->date('order_date');
            $table->date('delivery_date')->nullable();
            $table->string('status', 20)->default('draft');
            $table->string('payment_term', 20)->default('cod');
            $table->string('payment_term_description', 120)->nullable();
            $table->boolean('is_tax_inclusive')->default(false);
            $table->string('tax_dpp_type', 20)->default('100');
            $table->decimal('tax_rate', 5, 2)->default(11.00);
            $table->string('global_discount_type', 20)->default('amount');
            $table->decimal('global_discount_value', 18, 2)->default(0);
            $table->decimal('subtotal', 18, 2)->default(0);
            $table->decimal('discount_total', 18, 2)->default(0);
            $table->decimal('tax_total', 18, 2)->default(0);
            $table->decimal('grand_total', 18, 2)->default(0);
            $table->decimal('total_weight_kg', 12, 3)->default(0);
            $table->unsignedInteger('total_quantity_ea')->default(0);
            $table->json('metadata')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('live_chicken_purchase_orders');
    }
};
