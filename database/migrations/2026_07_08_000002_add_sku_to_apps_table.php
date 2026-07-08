<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Allineamento allo schema wm-package: sku è l'identificatore
// applicativo (bundle) sugli shard wm-package. Orchestrator lo
// riceve dal contratto v1 dell'export.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('apps', function (Blueprint $table) {
            $table->string('sku')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('apps', function (Blueprint $table) {
            $table->dropColumn('sku');
        });
    }
};
