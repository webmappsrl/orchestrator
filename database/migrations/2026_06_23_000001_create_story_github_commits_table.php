<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('story_github_commits', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('story_id')->index();
            $table->string('sha', 40);
            $table->string('repo');
            $table->string('author_email')->nullable();
            $table->timestamp('committed_at')->nullable();
            $table->timestamps();

            $table->unique(['story_id', 'sha']);
            $table->foreign('story_id')->references('id')->on('stories')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('story_github_commits');
    }
};
