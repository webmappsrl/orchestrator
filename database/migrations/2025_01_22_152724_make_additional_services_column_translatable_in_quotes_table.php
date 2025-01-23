<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up()
    {
        $quotes = DB::table('quotes')
            ->whereNotNull('additional_services')
            ->get(['id', 'additional_services']);


        foreach ($quotes as $quote) {
            $translatedData = [
                'it' => json_decode($quote->additional_services, true),
                'en' => []
            ];

            DB::table('quotes')
                ->where('id', $quote->id)
                ->update(['additional_services' => json_encode($translatedData)]);
        }
    }

    public function down()
    {
        $quotes = DB::table('quotes')
            ->whereNotNull('additional_services')
            ->get(['id', 'additional_services']);

        foreach ($quotes as $quote) {
            $data = json_decode($quote->additional_services, true);
            $italianData = $data['it'] ?? [];

            DB::table('quotes')
                ->where('id', $quote->id)
                ->update(['additional_services' => json_encode($italianData)]);
        }

        Schema::table('quotes', function (Blueprint $table) {
            $table->text('additional_services')->change();
        });
    }
};
