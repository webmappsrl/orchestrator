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
        Schema::table('stories', function (Blueprint $table) {
            // Remove tag column if it exists
            if (Schema::hasColumn('stories', 'tag')) {
                $table->dropColumn('tag');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stories', function (Blueprint $table) {
            // Re-add tag column if it doesn't exist
            if (!Schema::hasColumn('stories', 'tag')) {
                $table->string('tag')->nullable()->after('name');
            }
        });
    }
};
