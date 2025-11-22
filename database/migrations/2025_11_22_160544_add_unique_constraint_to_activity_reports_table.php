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
        Schema::table('activity_reports', function (Blueprint $table) {
            // Add unique constraint to prevent duplicate reports
            // A combination of owner_type, customer_id, organization_id, report_type, year, and month
            // should be unique to prevent duplicate reports
            // Note: PostgreSQL treats NULL != NULL, so this works correctly for nullable fields
            $table->unique(
                ['owner_type', 'customer_id', 'organization_id', 'report_type', 'year', 'month'],
                'activity_reports_unique'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('activity_reports', function (Blueprint $table) {
            $table->dropUnique('activity_reports_unique');
        });
    }
};
