<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('quotes')
            ->where('status', 'sent')
            ->update(['status' => 'presented']);
    }

    /**
     * Reverse the migrations.
     *
     * Nota: non è possibile distinguere in modo sicuro le quote
     * aggiornate da questa migrazione da quelle che erano già
     * in stato "presented", quindi il down rimane volutamente vuoto.
     */
    public function down(): void
    {
        //
    }
};

