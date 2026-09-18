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

            // Explicit lengths instead of nullableUuidMorphs(): the composite unique
            // key below must stay under InnoDB's 3072-byte limit on utf8mb4 (MySQL 8).
            // 2 x varchar(191) + 2 x char(36) + varchar(64) = 2,072 bytes.
            $table->string('subject_type', 191)->nullable();
            $table->uuid('subject_id')->nullable();
            $table->string('object_type', 191)->nullable();
            $table->uuid('object_id')->nullable();
            $table->string('type', 64)->default('mother');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->unique(
                ['subject_type', 'subject_id', 'object_type', 'object_id', 'type'],
                'pat_rel_subject_object_type_unique',
            );
            $table->index(['subject_type', 'subject_id']);
            $table->index(['object_type', 'object_id']);
            $table->index(['object_type', 'object_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patient_relationships');
    }
};
