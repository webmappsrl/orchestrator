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
            $table->foreignId('fundraising_project_id')->nullable()->constrained('fundraising_projects')->onDelete('set null');
            $table->index(['fundraising_project_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stories', function (Blueprint $table) {
            $table->dropForeign(['fundraising_project_id']);
            $table->dropIndex(['fundraising_project_id']);
            $table->dropColumn('fundraising_project_id');
        });
    }
};
