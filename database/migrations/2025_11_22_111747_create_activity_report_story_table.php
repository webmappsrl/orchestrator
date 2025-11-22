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
        Schema::create('activity_report_story', function (Blueprint $table) {
            $table->id();
            $table->foreignId('activity_report_id')->constrained('activity_reports')->onDelete('cascade');
            $table->foreignId('story_id')->constrained('stories')->onDelete('cascade');
            $table->timestamps();

            // Indice univoco per evitare duplicati
            $table->unique(['activity_report_id', 'story_id']);

            // Indici per performance
            $table->index('activity_report_id');
            $table->index('story_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('activity_report_story');
    }
};
