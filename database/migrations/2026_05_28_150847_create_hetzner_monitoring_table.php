<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('hetzner_monitoring', function (Blueprint $table) {
            $table->id();
            $table->jsonb('properties')->default('{}');
            $table->timestamps();
        });

        DB::statement("
            CREATE UNIQUE INDEX hetzner_monitoring_resource_unique
            ON hetzner_monitoring (
                (properties->>'project_slug'),
                (properties->>'resource_type'),
                ((properties->>'resource_id')::bigint)
            )
        ");

        DB::statement("
            CREATE INDEX hetzner_monitoring_project_type_idx
            ON hetzner_monitoring (
                (properties->>'project_slug'),
                (properties->>'resource_type')
            )
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hetzner_monitoring');
    }
};
