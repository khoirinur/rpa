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
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('module')->nullable();
            $table->string('action_type');
            $table->string('description');
            $table->nullableMorphs('subject');
            $table->foreignId('warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();
            $table->json('changes')->nullable();
            $table->json('revert_payload')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('performed_at');
            $table->timestamp('reverted_at')->nullable();
            $table->foreignId('reverted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('revert_reason')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['module', 'action_type']);
            $table->index('performed_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
