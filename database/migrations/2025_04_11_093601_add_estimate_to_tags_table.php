<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddEstimateToTagsTable extends Migration
{
    public function up()
    {
        Schema::table('tags', function (Blueprint $table) {
            $table->decimal('estimate', 8, 2)->nullable(); // Aggiungi il campo 'estimate'
        });
    }

    public function down()
    {
        Schema::table('tags', function (Blueprint $table) {
            $table->dropColumn('estimate'); // Rimuovi il campo 'estimate' se necessario
        });
    }
}
