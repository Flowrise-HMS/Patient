<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Models\Branch;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('patients', function (Blueprint $table) {
            $table->uuid('id')->unique()->primary();
            $table->uuid('global_uuid')->unique()->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->foreignIdFor(Branch::class)->nullable()->constrained()->cascadeOnDelete();
            $table->string('mrn')->unique()->nullable();
            $table->string('title')->nullable();
            $table->string('first_name');
            $table->string('middle_name')->nullable();
            $table->string('last_name');
            $table->dateTime('date_of_birth')->nullable();
            $table->boolean('is_date_of_birth_estimated')->default(false);
            $table->string('gender')->nullable();
            $table->string('blood_type')->nullable();
            $table->string('marital_status')->nullable();
            $table->string('education_level')->nullable();
            $table->string('occupation')->nullable();
            $table->string('nationality')->nullable();

            $table->string('phone')->nullable();
            $table->string('email')->nullable();

            $table->string('preferred_language')->nullable();
            $table->string('photo')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_deceased')->default(false);
            $table->dateTime('deceased_at')->nullable();
            $table->json('encrypted_fields')->nullable();
            $table->json('address')->nullable();
            $table->json('contact')->nullable();
            $table->json('meta')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('patients');
    }
};
