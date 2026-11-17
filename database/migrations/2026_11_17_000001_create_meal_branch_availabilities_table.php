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
        Schema::create('meal_branch_availabilities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meal_id')->constrained('meals')->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->boolean('is_available')->default(true);
            $table->timestamps();

            $table->unique(['meal_id', 'branch_id'], 'uq_meal_branch_availabilities_meal_branch');
            $table->index(['branch_id', 'is_available'], 'idx_meal_branch_availabilities_branch_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('meal_branch_availabilities');
    }
};
