<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotes', function (Blueprint $table) {
            $table->boolean('template')->default(false)->after('customer_id');
            $table->index(['customer_id', 'template']);
        });
    }

    public function down(): void
    {
        Schema::table('quotes', function (Blueprint $table) {
            $table->dropIndex(['customer_id', 'template']);
            $table->dropColumn('template');
        });
    }
};

