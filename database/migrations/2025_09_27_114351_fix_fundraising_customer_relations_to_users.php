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
        // Correggere la tabella fundraising_projects: cambiare lead_customer_id da customers a users
        Schema::table('fundraising_projects', function (Blueprint $table) {
            // Rimuovere la foreign key esistente
            $table->dropForeign(['lead_customer_id']);
            $table->dropIndex(['lead_customer_id']);
            
            // Rinominare la colonna per chiarezza
            $table->renameColumn('lead_customer_id', 'lead_user_id');
        });

        // Correggere la tabella fundraising_project_partners: cambiare customer_id da customers a users
        Schema::table('fundraising_project_partners', function (Blueprint $table) {
            // Rimuovere la foreign key esistente
            $table->dropForeign(['customer_id']);
            $table->dropIndex(['customer_id']);
            
            // Rinominare la colonna per chiarezza
            $table->renameColumn('customer_id', 'user_id');
        });

        // Ricreare le foreign key corrette
        Schema::table('fundraising_projects', function (Blueprint $table) {
            $table->foreign('lead_user_id')->references('id')->on('users');
            $table->index(['lead_user_id']);
        });

        Schema::table('fundraising_project_partners', function (Blueprint $table) {
            $table->foreign('user_id')->references('id')->on('users');
            $table->index(['user_id']);
            
            // Aggiornare il constraint unique
            $table->dropUnique(['fundraising_project_id', 'customer_id']);
            $table->unique(['fundraising_project_id', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Ripristinare le relazioni originali (customers)
        Schema::table('fundraising_projects', function (Blueprint $table) {
            $table->dropForeign(['lead_user_id']);
            $table->dropIndex(['lead_user_id']);
            $table->renameColumn('lead_user_id', 'lead_customer_id');
            $table->foreign('lead_customer_id')->references('id')->on('customers');
            $table->index(['lead_customer_id']);
        });

        Schema::table('fundraising_project_partners', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropIndex(['user_id']);
            $table->dropUnique(['fundraising_project_id', 'user_id']);
            $table->renameColumn('user_id', 'customer_id');
            $table->foreign('customer_id')->references('id')->on('customers');
            $table->index(['customer_id']);
            $table->unique(['fundraising_project_id', 'customer_id']);
        });
    }
};
