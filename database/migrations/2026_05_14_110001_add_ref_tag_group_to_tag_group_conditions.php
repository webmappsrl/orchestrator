<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tag_group_conditions', function (Blueprint $table) {
            $table->foreignId('ref_tag_group_id')
                ->nullable()
                ->after('tag_id')
                ->constrained('tag_groups')
                ->nullOnDelete();
        });
        // Rende tag_id nullable (raw SQL perché doctrine/dbal non è disponibile)
        DB::statement('ALTER TABLE tag_group_conditions ALTER COLUMN tag_id DROP NOT NULL');
    }

    public function down(): void
    {
        // Ripristina NOT NULL solo se non ci sono righe con tag_id NULL
        DB::statement('ALTER TABLE tag_group_conditions ALTER COLUMN tag_id SET NOT NULL');
        Schema::table('tag_group_conditions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('ref_tag_group_id');
        });
    }
};
