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
        if (Schema::hasTable('kitchen_tickets')) {
            Schema::table('kitchen_tickets', function (Blueprint $table) {
                if (! Schema::hasIndex('kitchen_tickets', 'idx_kds_signature_covering')) {
                    $table->index(['branch_id', 'served_at', 'status', 'updated_at'], 'idx_kds_signature_covering');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('kitchen_tickets')) {
            Schema::table('kitchen_tickets', function (Blueprint $table) {
                if (Schema::hasIndex('kitchen_tickets', 'idx_kds_signature_covering')) {
                    $table->dropIndex('idx_kds_signature_covering');
                }
            });
        }
    }
};
