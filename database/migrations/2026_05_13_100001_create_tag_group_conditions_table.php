<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tag_group_conditions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tag_group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('group_index');
            $table->timestamps();

            $table->unique(['tag_group_id', 'tag_id', 'group_index']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tag_group_conditions');
    }
};
