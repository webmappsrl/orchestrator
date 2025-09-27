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
        Schema::create('fundraising_opportunities', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // Nome del bando
            $table->string('official_url')->nullable(); // URL ufficiale
            $table->decimal('endowment_fund', 15, 2)->nullable(); // Fondo di dotazione
            $table->date('deadline'); // Data di scadenza
            $table->string('program_name')->nullable(); // Nome del programma
            $table->string('sponsor')->nullable(); // Sponsor del bando
            $table->decimal('cofinancing_quota', 5, 2)->nullable(); // Quota cofinanziamento (percentuale)
            $table->decimal('max_contribution', 15, 2)->nullable(); // Contributo massimo
            $table->enum('territorial_scope', [
                'cooperation',
                'european', 
                'national',
                'regional',
                'territorial',
                'municipalities'
            ])->default('national'); // Scope territoriale
            $table->text('beneficiary_requirements')->nullable(); // Requisiti del beneficiario
            $table->text('lead_requirements')->nullable(); // Requisiti del capofila
            $table->foreignId('created_by')->constrained('users'); // Utente che ha creato il fro
            $table->foreignId('responsible_user_id')->constrained('users'); // Utente responsabile (solo fundraising)
            $table->timestamps();
            
            // Indici per performance
            $table->index(['deadline']);
            $table->index(['territorial_scope']);
            $table->index(['created_by']);
            $table->index(['responsible_user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fundraising_opportunities');
    }
};
