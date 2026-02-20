<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Normalizza gli stati quote: unifica i duplicati "closed won offer" e "closed lost offer"
     * negli stati base "closed won" e "closed lost".
     */
    public function up(): void
    {
        DB::table('quotes')
            ->where('status', 'closed won offer')
            ->update(['status' => 'closed won']);

        DB::table('quotes')
            ->where('status', 'closed lost offer')
            ->update(['status' => 'closed lost']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Non ripristiniamo i valori: non è possibile distinguere quali erano "offer"
    }
};
