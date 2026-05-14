<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tag_group_stories', function (Blueprint $table) {
            $table->foreignId('tag_group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('story_id')->constrained()->cascadeOnDelete();
            $table->primary(['tag_group_id', 'story_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tag_group_stories');
    }
};
