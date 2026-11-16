<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Diario delle attività su trattative, prodotti e task (oc:8724).
 *
 * Le FK si svuotano alla cancellazione di ciò a cui puntano: la riga resta
 * leggibile grazie alle copie di titolo e nome prese al momento dell'evento.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quote_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quote_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('task_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('quote_title')->nullable();
            $table->string('task_title')->nullable();
            $table->string('user_name')->nullable();
            $table->string('event');
            $table->json('changes')->nullable();
            $table->timestamps();

            $table->index('created_at');
            $table->index(['quote_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quote_activities');
    }
};
