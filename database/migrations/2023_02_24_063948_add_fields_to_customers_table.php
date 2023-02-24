<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * 
     */
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->text('notes')->nullable();
            $table->string('hs_id')->nullable();
            $table->string('domain_name')->nullable();
            $table->string('full_name')->nullable();
            $table->boolean('has_subscription')->nullable();
            $table->float('subscription_amount')->nullable();
            $table->date('subscription_last_payment')->nullable();
            $table->integer('subscription_last_covered_year')->nullable();
            $table->string('subscription_last_invoice')->nullable();
            $table->dropColumn('description');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn('notes');
            $table->dropColumn('hs_id');
            $table->dropColumn('domain_name');
            $table->dropColumn('full_name');
            $table->dropColumn('has_subscription');
            $table->dropColumn('subscription_amount');
            $table->dropColumn('subscription_last_payment');
            $table->dropColumn('subscription_last_covered_year');
            $table->dropColumn('subscription_last_invoice');
        });
    }
};
