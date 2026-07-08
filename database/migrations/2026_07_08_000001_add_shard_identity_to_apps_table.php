<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * ⚠️ Il down() è per il solo rollback pre-produzione: dopo il primo sync
 * multi-shard possono esistere app_id duplicati tra shard e il ripristino
 * dell'unique semplice fallirebbe. Il rollback operativo è disattivare
 * gli shard in config/shards.php (nessuna perdita dati).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('apps', function (Blueprint $table) {
            $table->string('shard')->nullable();
            $table->timestamp('removed_from_shard_at')->nullable();
        });

        // Tutte le app esistenti provengono dall'import geohub.
        DB::table('apps')->update(['shard' => 'geohub']);
        DB::statement('ALTER TABLE apps ALTER COLUMN shard SET NOT NULL');

        Schema::table('apps', function (Blueprint $table) {
            $table->dropUnique('apps_app_id_unique');
            $table->unique(['shard', 'app_id']);
        });
    }

    public function down(): void
    {
        Schema::table('apps', function (Blueprint $table) {
            $table->dropUnique(['shard', 'app_id']);
            $table->unique('app_id', 'apps_app_id_unique');
            $table->dropColumn(['shard', 'removed_from_shard_at']);
        });
    }
};
