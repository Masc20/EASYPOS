<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('activity_logs')) {
            return;
        }

        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_name', 150)->nullable();
            $table->string('actor_emp_id', 50)->nullable();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->string('category', 40)->index();
            $table->string('event', 80)->index();
            $table->string('status', 20)->default('success')->index();
            $table->string('description', 500);
            $table->string('subject_type', 120)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['branch_id', 'created_at'], 'idx_activity_logs_branch_created');
            $table->index(['user_id', 'created_at'], 'idx_activity_logs_user_created');
            $table->index(['event', 'status'], 'idx_activity_logs_event_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
