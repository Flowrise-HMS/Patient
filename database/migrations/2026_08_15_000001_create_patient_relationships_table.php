<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patient_relationships', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->nullableUuidMorphs('subject');
            $table->nullableUuidMorphs('object');
            $table->string('type')->default('mother');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->unique(
                ['subject_type', 'subject_id', 'object_type', 'object_id', 'type'],
                'pat_rel_subject_object_type_unique',
            );
            $table->index(['object_type', 'object_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patient_relationships');
    }
};
