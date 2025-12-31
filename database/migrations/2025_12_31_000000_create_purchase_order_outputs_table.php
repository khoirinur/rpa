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
        Schema::create('purchase_order_outputs', function (Blueprint $table) {
            $table->id();
            $table->string('document_number', 40)->unique();
            $table->string('document_title', 160);
            $table->foreignId('live_chicken_purchase_order_id')
                ->constrained('live_chicken_purchase_orders')
                ->restrictOnDelete();
            $table->foreignId('warehouse_id')
                ->constrained('warehouses')
                ->restrictOnDelete();
            $table->foreignId('printed_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->date('document_date');
            $table->string('status', 20)->default('draft');
            $table->string('layout_template', 40)->default('standard');
            $table->json('document_sections')->nullable();
            $table->json('attachments')->nullable();
            $table->json('metadata')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('printed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'warehouse_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_order_outputs');
    }
};
