<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('stories', function (Blueprint $table) {
            // Aggiungiamo la colonna embedding di tipo vector
            // La dimensione 1536 è quella standard per text-embedding-3-small di OpenAI
            // Il metodo vector() è disponibile tramite il macro di pgvector/pgvector
            $table->vector('embedding', 1536)->nullable()->after('customer_request');
        });

        // Creiamo un indice HNSW per ricerche veloci di similarità
        // HNSW è più veloce di IVFFlat per ricerche di similarità
        DB::statement('CREATE INDEX IF NOT EXISTS stories_embedding_idx ON stories USING hnsw (embedding vector_cosine_ops)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stories', function (Blueprint $table) {
            // Rimuoviamo l'indice prima di rimuovere la colonna
            DB::statement('DROP INDEX IF EXISTS stories_embedding_idx');
            $table->dropColumn('embedding');
        });
    }
};
