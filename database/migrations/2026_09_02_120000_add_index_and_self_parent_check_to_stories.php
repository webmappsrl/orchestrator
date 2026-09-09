<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stories', function (Blueprint $table) {
            $table->index('parent_id', 'stories_parent_id_index');
        });

        DB::statement('ALTER TABLE stories ADD CONSTRAINT stories_parent_id_not_self CHECK (parent_id IS NULL OR parent_id <> id)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE stories DROP CONSTRAINT IF EXISTS stories_parent_id_not_self');

        Schema::table('stories', function (Blueprint $table) {
            $table->dropIndex('stories_parent_id_index');
        });
    }
};
