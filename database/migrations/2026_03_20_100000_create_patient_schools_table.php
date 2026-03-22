<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patient_schools', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('patient_id')
                ->constrained()
                ->cascadeOnDelete();

            // School Information
            $table->string('school_name');
            $table->string('school_id')->nullable();
            $table->string('school_type')->nullable(); // primary, secondary, tertiary, vocational
            $table->text('school_address')->nullable();
            $table->string('school_phone')->nullable();
            $table->string('school_email')->nullable();

            // Academic Details
            $table->string('level')->nullable(); // Primary 1-6, JHS 1-3, SHS 1-3, Year 1-6
            $table->string('class_name')->nullable(); // e.g., "Primary 5", "Form 3", "100L"
            $table->string('classroom')->nullable(); // e.g., "Room A1", "Block 2"
            $table->string('course')->nullable(); // For tertiary students
            $table->string('course_duration')->nullable(); // e.g., "4 years"
            $table->string('year_of_study')->nullable(); // e.g., "Year 1", "Final Year"

            // Hostel Information
            $table->string('hostel')->nullable(); // Hostel name
            $table->string('hostel_room')->nullable(); // Room number

            // Status & Dates
            $table->date('admission_date')->nullable();
            $table->date('graduation_date')->nullable();
            $table->boolean('is_current')->default(false);
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->index(['patient_id', 'is_current']);
            $table->index(['school_type']);
            $table->index(['level']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patient_schools');
    }
};
