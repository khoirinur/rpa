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
        Schema::table('live_chicken_purchase_orders', function (Blueprint $table) {
            $table->foreignId('product_id')
                ->nullable()
                ->after('destination_warehouse_id')
                ->constrained('products')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('live_chicken_purchase_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('product_id');
        });
    }
};
