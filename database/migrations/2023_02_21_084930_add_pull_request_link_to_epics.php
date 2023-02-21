<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('stories', function (Blueprint $table) {
            $table->dropColumn('pull_request_link');
        });
        Schema::table('epics', function (Blueprint $table) {
            $table->string('pull_request_link')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('epics', function (Blueprint $table) {
            $table->dropColumn('pull_request_link');
        });
        Schema::table('stories', function (Blueprint $table) {
            $table->string('pull_request_link')->nullable();
        });
    }
};
