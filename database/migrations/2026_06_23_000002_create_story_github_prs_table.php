<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('story_github_prs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('story_id')->index();
            $table->string('repo');
            $table->unsignedInteger('pr_number');
            $table->unsignedInteger('change_requests_count')->default(0);
            $table->timestamp('merged_at')->nullable();
            $table->timestamps();

            $table->unique(['story_id', 'repo', 'pr_number']);
            $table->foreign('story_id')->references('id')->on('stories')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('story_github_prs');
    }
};
