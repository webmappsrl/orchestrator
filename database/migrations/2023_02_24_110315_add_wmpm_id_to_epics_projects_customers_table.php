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
        Schema::table('epics', function (Blueprint $table) {
            $table->integer('wmpm_id')->unique()->nullable();
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->integer('wmpm_id')->unique()->nullable();
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->integer('wmpm_id')->unique()->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('epics', function (Blueprint $table) {
            $table->dropColumn('wmpm_id');
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn('wmpm_id');
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn('wmpm_id');
        });
    }
};
