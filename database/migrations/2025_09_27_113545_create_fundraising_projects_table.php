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
        Schema::create('fundraising_projects', function (Blueprint $table) {
            $table->id();
            $table->string('title'); // Titolo del progetto
            $table->foreignId('fundraising_opportunity_id')->constrained('fundraising_opportunities'); // Riferimento al fro
            $table->foreignId('lead_customer_id')->constrained('customers'); // Capofila (riferimento a customer)
            $table->foreignId('created_by')->constrained('users'); // Utente che ha creato il frp
            $table->foreignId('responsible_user_id')->constrained('users'); // Utente con ruolo fundraising responsabile
            $table->text('description')->nullable(); // Descrizione del progetto
            $table->enum('status', [
                'draft',
                'submitted',
                'approved',
                'rejected',
                'completed'
            ])->default('draft'); // Stato del progetto
            $table->decimal('requested_amount', 15, 2)->nullable(); // Importo richiesto
            $table->decimal('approved_amount', 15, 2)->nullable(); // Importo approvato
            $table->date('submission_date')->nullable(); // Data di presentazione
            $table->date('decision_date')->nullable(); // Data di decisione
            $table->timestamps();
            
            // Indici per performance
            $table->index(['fundraising_opportunity_id']);
            $table->index(['lead_customer_id']);
            $table->index(['created_by']);
            $table->index(['responsible_user_id']);
            $table->index(['status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fundraising_projects');
    }
};
