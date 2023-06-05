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
        Schema::create('deadlineables', function (Blueprint $table) {
            $table->integer('deadlineable_id')->unsigned();
            $table->string('deadlineable_type');
            $table->foreignId('deadline_id')->references('id')->on('deadlines')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('deadlineables');
    }
};
