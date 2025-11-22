<?php

use App\Enums\OwnerType;
use App\Enums\ReportType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('activity_reports', function (Blueprint $table) {
            $table->id();
            $table->enum('owner_type', OwnerType::values())->default(OwnerType::Customer->value);
            $table->foreignId('customer_id')->nullable()->constrained('users')->onDelete('cascade');
            $table->foreignId('organization_id')->nullable()->constrained('organizations')->onDelete('cascade');
            $table->enum('report_type', ReportType::values())->default(ReportType::Monthly->value);
            $table->integer('year');
            $table->integer('month')->nullable(); // Solo se report_type = monthly
            $table->string('pdf_url')->nullable();
            $table->timestamps();

            // Indici per performance
            $table->index(['owner_type', 'customer_id']);
            $table->index(['owner_type', 'organization_id']);
            $table->index(['report_type', 'year', 'month']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('activity_reports');
    }
};
