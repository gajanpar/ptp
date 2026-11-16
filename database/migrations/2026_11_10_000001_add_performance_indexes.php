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
        Schema::table('evaluations', function (Blueprint $table) {
            $table->index(['period_year', 'period_month'], 'idx_evaluations_period_year_month');
        });

        Schema::table('evaluation_details', function (Blueprint $table) {
            $table->index('subcategory_id', 'idx_eval_details_subcategory_id');
        });

        Schema::table('edit_requests', function (Blueprint $table) {
            $table->index('status', 'idx_edit_requests_status');
        });

        Schema::table('employees', function (Blueprint $table) {
            $table->index('nik', 'idx_employees_nik');
            $table->index('is_active', 'idx_employees_is_active');
        });

        Schema::table('activity_logs', function (Blueprint $table) {
            $table->index(['action', 'created_at'], 'idx_activity_logs_action_created_at');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->index(['role', 'is_active'], 'idx_users_role_is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('idx_users_role_is_active');
        });

        Schema::table('activity_logs', function (Blueprint $table) {
            $table->dropIndex('idx_activity_logs_action_created_at');
        });

        Schema::table('employees', function (Blueprint $table) {
            $table->dropIndex('idx_employees_nik');
            $table->dropIndex('idx_employees_is_active');
        });

        Schema::table('edit_requests', function (Blueprint $table) {
            $table->dropIndex('idx_edit_requests_status');
        });

        Schema::table('evaluation_details', function (Blueprint $table) {
            $table->dropIndex('idx_eval_details_subcategory_id');
        });

        Schema::table('evaluations', function (Blueprint $table) {
            $table->dropIndex('idx_evaluations_period_year_month');
        });
    }
};
