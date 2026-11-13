<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('order_items')) {
            Schema::table('order_items', function (Blueprint $table) {
                if (! Schema::hasIndex('order_items', 'idx_order_items_reporting_covering')) {
                    $table->index(['order_id', 'meal_id', 'quantity', 'subtotal'], 'idx_order_items_reporting_covering');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('order_items')) {
            Schema::table('order_items', function (Blueprint $table) {
                if (Schema::hasIndex('order_items', 'idx_order_items_reporting_covering')) {
                    $table->dropIndex('idx_order_items_reporting_covering');
                }
            });
        }
    }
};
