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
        Schema::table('customers', function (Blueprint $table) {
            $table->integer('score_cash')->nullable();
            $table->integer('score_pain')->nullable();
            $table->integer('score_business')->nullable();
            $table->integer('score')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn('score_cash');
            $table->dropColumn('score_pain');
            $table->dropColumn('score_business');
            $table->dropColumn('score');
        });
    }
};
