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
        Schema::create('inventory_adjustments', function (Blueprint $table) {
            $table->id();
            $table->string('adjustment_number', 30)->unique();
            $table->date('adjustment_date');
            $table->foreignId('default_warehouse_id')
                ->nullable()
                ->constrained('warehouses')
                ->nullOnDelete();
            $table->foreignId('adjustment_account_id')
                ->nullable()
                ->constrained('chart_of_accounts')
                ->nullOnDelete();
            $table->decimal('total_addition_value', 18, 2)->default(0);
            $table->decimal('total_reduction_value', 18, 2)->default(0);
            $table->decimal('total_set_value', 18, 2)->default(0);
            $table->json('metadata')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('inventory_adjustment_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_adjustment_id')
                ->constrained('inventory_adjustments')
                ->cascadeOnDelete();
            $table->foreignId('product_id')
                ->nullable()
                ->constrained('products')
                ->nullOnDelete();
            $table->foreignId('warehouse_id')
                ->nullable()
                ->constrained('warehouses')
                ->nullOnDelete();
            $table->foreignId('unit_id')
                ->nullable()
                ->constrained('units')
                ->nullOnDelete();
            $table->string('item_code', 30)->nullable();
            $table->string('item_name', 180)->nullable();
            $table->enum('adjustment_type', ['addition', 'reduction', 'set']);
            $table->decimal('quantity', 18, 3)->default(0);
            $table->decimal('target_quantity', 18, 3)->nullable();
            $table->decimal('current_stock_snapshot', 18, 3)->nullable();
            $table->decimal('unit_cost', 18, 2)->nullable();
            $table->decimal('total_cost', 18, 2)->nullable();
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
        Schema::dropIfExists('inventory_adjustment_items');
        Schema::dropIfExists('inventory_adjustments');
    }
};
