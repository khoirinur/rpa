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
        Schema::create('chart_of_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name');
            $table->string('type', 20);
            $table->enum('normal_balance', ['debit', 'credit'])->default('debit');
            $table->foreignId('parent_id')
                ->nullable()
                ->constrained('chart_of_accounts')
                ->nullOnDelete();
            $table->unsignedTinyInteger('level')->default(1);
            $table->boolean('is_summary')->default(false);
            $table->boolean('is_active')->default(true);
            $table->decimal('opening_balance', 18, 2)->default(0);
            $table->foreignId('default_warehouse_id')
                ->nullable()
                ->constrained('warehouses')
                ->nullOnDelete();
            $table->text('description')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chart_of_accounts');
    }
};
