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
        if (Schema::hasTable('orders')) {
            Schema::table('orders', function (Blueprint $table) {
                if (! Schema::hasIndex('orders', 'idx_orders_global_reporting')) {
                    $table->index(['created_at', 'payment_status', 'status'], 'idx_orders_global_reporting');
                }
                if (! Schema::hasIndex('orders', 'idx_orders_branch_reporting')) {
                    $table->index(['branch_id', 'created_at', 'payment_status', 'status'], 'idx_orders_branch_reporting');
                }
                if (! Schema::hasIndex('orders', 'idx_orders_cashier_reporting')) {
                    $table->index(['cashier_id', 'created_at'], 'idx_orders_cashier_reporting');
                }
            });
        }

        if (Schema::hasTable('payments')) {
            Schema::table('payments', function (Blueprint $table) {
                if (! Schema::hasIndex('payments', 'idx_payments_branch_reconciliation')) {
                    $table->index(['branch_id', 'status', 'created_at'], 'idx_payments_branch_reconciliation');
                }
                if (! Schema::hasIndex('payments', 'idx_payments_global_reconciliation')) {
                    $table->index(['status', 'created_at'], 'idx_payments_global_reconciliation');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('orders')) {
            Schema::table('orders', function (Blueprint $table) {
                if (Schema::hasIndex('orders', 'idx_orders_global_reporting')) {
                    $table->dropIndex('idx_orders_global_reporting');
                }
                if (Schema::hasIndex('orders', 'idx_orders_branch_reporting')) {
                    $table->dropIndex('idx_orders_branch_reporting');
                }
                if (Schema::hasIndex('orders', 'idx_orders_cashier_reporting')) {
                    $table->dropIndex('idx_orders_cashier_reporting');
                }
            });
        }

        if (Schema::hasTable('payments')) {
            Schema::table('payments', function (Blueprint $table) {
                if (Schema::hasIndex('payments', 'idx_payments_branch_reconciliation')) {
                    $table->dropIndex('idx_payments_branch_reconciliation');
                }
                if (Schema::hasIndex('payments', 'idx_payments_global_reconciliation')) {
                    $table->dropIndex('idx_payments_global_reconciliation');
                }
            });
        }
    }
};
