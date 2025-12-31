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
        Schema::create('goods_receipts', function (Blueprint $table): void {
            $table->id();
            $table->string('receipt_number', 30)->unique();
            $table->foreignId('live_chicken_purchase_order_id')
                ->nullable()
                ->constrained('live_chicken_purchase_orders')
                ->nullOnDelete();
            $table->foreignId('supplier_id')
                ->constrained('suppliers')
                ->restrictOnDelete();
            $table->foreignId('destination_warehouse_id')
                ->constrained('warehouses')
                ->restrictOnDelete();
            $table->text('delivery_address')->nullable();
            $table->date('expected_delivery_date')->nullable();
            $table->dateTime('received_at')->nullable();
            $table->string('status', 30)->default('draft');
            $table->string('supplier_delivery_note_number', 50)->nullable();
            $table->string('vehicle_plate_number', 30)->nullable();
            $table->decimal('arrival_temperature_c', 5, 2)->nullable();
            $table->string('arrival_inspector_name', 120)->nullable();
            $table->json('arrival_checks')->nullable();
            $table->text('arrival_notes')->nullable();
            $table->json('attachments')->nullable();
            $table->json('additional_costs')->nullable();
            $table->unsignedInteger('total_item_count')->default(0);
            $table->decimal('total_received_weight_kg', 12, 3)->default(0);
            $table->decimal('total_received_quantity_ea', 12, 3)->default(0);
            $table->decimal('loss_weight_kg', 12, 3)->default(0);
            $table->decimal('loss_percentage', 5, 2)->default(0);
            $table->decimal('loss_quantity_ea', 12, 3)->default(0);
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('goods_receipts');
    }
};
