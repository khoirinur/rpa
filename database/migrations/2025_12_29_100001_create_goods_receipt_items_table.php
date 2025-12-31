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
        Schema::create('goods_receipt_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('goods_receipt_id')
                ->constrained('goods_receipts')
                ->cascadeOnDelete();
            $table->foreignId('product_id')
                ->nullable()
                ->constrained('products')
                ->nullOnDelete();
            $table->foreignId('warehouse_id')
                ->nullable()
                ->constrained('warehouses')
                ->nullOnDelete();
            $table->string('item_code', 30)->nullable();
            $table->string('item_name', 120);
            $table->string('unit', 10)->default('kg');
            $table->decimal('ordered_quantity', 12, 3)->default(0);
            $table->decimal('ordered_weight_kg', 12, 3)->default(0);
            $table->decimal('received_quantity', 12, 3)->default(0);
            $table->decimal('received_weight_kg', 12, 3)->default(0);
            $table->decimal('loss_quantity', 12, 3)->default(0);
            $table->decimal('loss_weight_kg', 12, 3)->default(0);
            $table->decimal('tolerance_percentage', 5, 2)->default(0);
            $table->boolean('is_returned')->default(false);
            $table->string('status', 20)->default('pending');
            $table->text('qc_notes')->nullable();
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
        Schema::dropIfExists('goods_receipt_items');
    }
};
