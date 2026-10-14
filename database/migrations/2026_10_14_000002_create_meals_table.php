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
        Schema::create('meals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('category_id')->constrained('meal_categories')->restrictOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name', 150);
            $table->string('menu_code', 20)->nullable()->index('idx_meals_menu_code');
            $table->text('description')->nullable();
            $table->decimal('price', 10, 2);
            $table->decimal('cost_price', 10, 2)->nullable();
            $table->boolean('is_available')->default(true);
            $table->boolean('track_stock')->default(false);
            $table->integer('stock_quantity')->nullable();
            $table->json('kitchen_tags')->default('[]');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['branch_id', 'category_id', 'is_active', 'is_available'], 'idx_meals_pos_catalog');
        });

        // PostgreSQL Partial Index for sub-millisecond POS menu queries
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('CREATE INDEX idx_meals_active_pos ON meals (branch_id, category_id, price) WHERE deleted_at IS NULL AND is_available = true');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('meals');
    }
};
