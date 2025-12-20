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
        Schema::table('chart_of_accounts', function (Blueprint $table): void {
            if (Schema::hasColumn('chart_of_accounts', 'normal_balance')) {
                $table->dropColumn('normal_balance');
            }

            if (Schema::hasColumn('chart_of_accounts', 'default_warehouse_id')) {
                $table->dropConstrainedForeignId('default_warehouse_id');
            }
        });

        Schema::table('chart_of_account_imports', function (Blueprint $table): void {
            if (Schema::hasColumn('chart_of_account_imports', 'default_warehouse_id')) {
                $table->dropConstrainedForeignId('default_warehouse_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('chart_of_accounts', function (Blueprint $table): void {
            if (! Schema::hasColumn('chart_of_accounts', 'normal_balance')) {
                $table->enum('normal_balance', ['debit', 'credit'])->default('debit');
            }

            if (! Schema::hasColumn('chart_of_accounts', 'default_warehouse_id')) {
                $table->foreignId('default_warehouse_id')
                    ->nullable()
                    ->constrained('warehouses')
                    ->nullOnDelete();
            }
        });

        Schema::table('chart_of_account_imports', function (Blueprint $table): void {
            if (! Schema::hasColumn('chart_of_account_imports', 'default_warehouse_id')) {
                $table->foreignId('default_warehouse_id')
                    ->nullable()
                    ->constrained('warehouses')
                    ->nullOnDelete();
            }
        });
    }
};
