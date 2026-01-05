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
        Schema::create('inventory_balances', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')
                ->constrained('products')
                ->cascadeOnDelete();
            $table->foreignId('warehouse_id')
                ->constrained('warehouses')
                ->cascadeOnDelete();
            $table->foreignId('unit_id')
                ->nullable()
                ->constrained('units')
                ->nullOnDelete();
            $table->decimal('on_hand_quantity', 18, 3)->default(0);
            $table->decimal('incoming_quantity', 18, 3)->default(0);
            $table->decimal('reserved_quantity', 18, 3)->default(0);
            $table->decimal('available_quantity', 18, 3)->default(0);
            $table->decimal('average_cost', 18, 4)->default(0);
            $table->timestamp('last_transaction_at')->nullable();
            $table->string('last_source_type')->nullable();
            $table->unsignedBigInteger('last_source_id')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['product_id', 'warehouse_id', 'unit_id'], 'inventory_balances_product_warehouse_unit_unique');
            $table->index(['warehouse_id', 'product_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_balances');
    }
};
