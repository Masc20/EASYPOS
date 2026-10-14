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
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number', 50)->unique();
            $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();
            $table->foreignId('cashier_id')->constrained('users')->restrictOnDelete();
            $table->string('order_type', 20)->default('dine_in'); // dine_in, takeout, delivery
            $table->string('table_number', 20)->nullable();
            $table->string('customer_name', 100)->nullable();
            $table->decimal('subtotal', 10, 2)->default(0.00);
            $table->decimal('discount_amount', 10, 2)->default(0.00);
            $table->decimal('tax_amount', 10, 2)->default(0.00);
            $table->decimal('total_amount', 10, 2)->default(0.00);
            $table->string('status', 30)->default('pending'); // pending, in_kitchen, completed, cancelled
            $table->string('payment_status', 30)->default('unpaid'); // unpaid, partially_paid, paid, refunded
            $table->text('notes')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['branch_id', 'status', 'created_at'], 'idx_orders_branch_status');
        });

        // PostgreSQL Partial Index for active uncompleted orders
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('CREATE INDEX idx_orders_active_uncompleted ON orders (branch_id, status, created_at) WHERE completed_at IS NULL');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
