<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('meal_branch_availabilities')) {
            return;
        }

        Schema::table('meal_branch_availabilities', function (Blueprint $table) {
            if (! Schema::hasColumn('meal_branch_availabilities', 'track_stock')) {
                $table->boolean('track_stock')->nullable()->after('is_available');
            }
            if (! Schema::hasColumn('meal_branch_availabilities', 'stock_quantity')) {
                $table->integer('stock_quantity')->nullable()->after('track_stock');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('meal_branch_availabilities')) {
            return;
        }

        Schema::table('meal_branch_availabilities', function (Blueprint $table) {
            $drop = array_values(array_filter([
                Schema::hasColumn('meal_branch_availabilities', 'stock_quantity') ? 'stock_quantity' : null,
                Schema::hasColumn('meal_branch_availabilities', 'track_stock') ? 'track_stock' : null,
            ]));

            if ($drop !== []) {
                $table->dropColumn($drop);
            }
        });
    }
};
