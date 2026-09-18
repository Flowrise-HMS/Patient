<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patient_merges', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('source_patient_id')->constrained('patients')->restrictOnDelete();
            $table->foreignUuid('target_patient_id')->constrained('patients')->restrictOnDelete();
            $table->foreignUuid('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->unsignedBigInteger('merged_by')->nullable();
            $table->string('source_mrn')->nullable();
            $table->string('target_mrn')->nullable();
            $table->json('moved_counts')->nullable();
            $table->json('filled_fields')->nullable();
            $table->longText('source_snapshot')->nullable(); // encrypted:array cast, so not a JSON column
            $table->text('reason')->nullable();
            $table->timestamps();

            $table->index(['target_patient_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patient_merges');
    }
};
