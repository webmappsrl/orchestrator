<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotes', function (Blueprint $table) {
            $table->unsignedInteger('priority')->nullable()->after('status');
            $table->index(['status', 'priority']);
        });

        // Backfill so existing quotes have a deterministic order per status.
        $statuses = DB::table('quotes')
            ->select('status')
            ->distinct()
            ->pluck('status');

        foreach ($statuses as $status) {
            $ids = DB::table('quotes')
                ->where('status', $status)
                ->orderBy('updated_at')
                ->orderBy('id')
                ->pluck('id');

            foreach ($ids as $priority => $id) {
                DB::table('quotes')
                    ->where('id', $id)
                    ->update(['priority' => $priority]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('quotes', function (Blueprint $table) {
            $table->dropIndex(['status', 'priority']);
            $table->dropColumn('priority');
        });
    }
};

