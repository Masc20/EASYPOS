<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('kitchen_tickets', function (Blueprint $table) {
            $table->id();
            $table->string('ticket_number', 30);
            $table->foreignId('order_id')->unique()->constrained('orders')->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();
            $table->foreignId('cook_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 20)->default('new'); // new, in_prep, ready, served, cancelled
            $table->string('priority', 10)->default('normal'); // normal, rush
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ready_at')->nullable();
            $table->timestamp('served_at')->nullable();
            $table->timestamps();

            $table->index(['branch_id', 'status', 'created_at'], 'idx_kds_live_queue');
        });

        // PostgreSQL Partial Index: Only indexes active tickets not yet cleared/served from the pass
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('CREATE INDEX idx_kds_active_queue ON kitchen_tickets (branch_id, priority, created_at) WHERE served_at IS NULL');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kitchen_tickets');
    }
};
