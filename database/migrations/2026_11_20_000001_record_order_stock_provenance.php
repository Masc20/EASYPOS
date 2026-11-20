<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            // Null identifies historical rows whose source must be inferred.
            $table->string('stock_source', 10)->nullable();
        });
        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('stock_consumed_at')->nullable();
        });
        DB::table('orders')->where(function ($query) {
            $query->where('status', 'completed')->orWhereNotNull('completed_at')
                ->orWhereExists(function ($tickets) {
                    $tickets->selectRaw('1')->from('kitchen_tickets')
                        ->whereColumn('kitchen_tickets.order_id', 'orders.id')
                        ->where(function ($served) {
                            $served->where('status', 'served')->orWhereNotNull('served_at');
                        });
                });
        })->update(['stock_consumed_at' => DB::raw('COALESCE(completed_at, updated_at, created_at, CURRENT_TIMESTAMP)')]);
    }

    public function down(): void
    {
        Schema::table('order_items', fn (Blueprint $table) => $table->dropColumn('stock_source'));
        Schema::table('orders', fn (Blueprint $table) => $table->dropColumn('stock_consumed_at'));
    }
};
