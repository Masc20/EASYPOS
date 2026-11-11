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
        Schema::create('branch_daily_sequences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->date('sequence_date');
            $table->string('sequence_type', 30); // 'order', 'kitchen_ticket'
            $table->unsignedInteger('current_value')->default(0);
            $table->timestamps();

            $table->unique(['branch_id', 'sequence_date', 'sequence_type'], 'uk_branch_date_seq_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('branch_daily_sequences');
    }
};
