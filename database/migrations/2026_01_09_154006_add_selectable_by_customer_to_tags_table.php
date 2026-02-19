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
        Schema::table('tags', function (Blueprint $table) {
            // Check if column already exists before adding
            if (!Schema::hasColumn('tags', 'selectable_by_customer')) {
                $table->boolean('selectable_by_customer')->default(false)->after('abstract');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tags', function (Blueprint $table) {
            if (Schema::hasColumn('tags', 'selectable_by_customer')) {
                $table->dropColumn('selectable_by_customer');
            }
        });
    }
};
