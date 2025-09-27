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
        Schema::create('fundraising_project_partners', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fundraising_project_id')->constrained('fundraising_projects')->onDelete('cascade');
            $table->foreignId('customer_id')->constrained('customers')->onDelete('cascade');
            $table->timestamps();
            
            // Indici per performance
            $table->index(['fundraising_project_id']);
            $table->index(['customer_id']);
            
            // Evita duplicati
            $table->unique(['fundraising_project_id', 'customer_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fundraising_project_partners');
    }
};
