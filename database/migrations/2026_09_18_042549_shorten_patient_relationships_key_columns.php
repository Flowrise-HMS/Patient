<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Databases created before the key-length fix in
 * 2026_08_15_000001_create_patient_relationships_table still carry varchar(255)
 * morph/type columns. Shrink them so the composite unique key fits MySQL 8's
 * 3072-byte index limit; values are class names and short enum values.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('patient_relationships')) {
            return;
        }

        Schema::table('patient_relationships', function (Blueprint $table) {
            $table->string('subject_type', 191)->nullable()->change();
            $table->string('object_type', 191)->nullable()->change();
            $table->string('type', 64)->default('mother')->change();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('patient_relationships')) {
            return;
        }

        Schema::table('patient_relationships', function (Blueprint $table) {
            $table->string('subject_type')->nullable()->change();
            $table->string('object_type')->nullable()->change();
            $table->string('type')->default('mother')->change();
        });
    }
};
