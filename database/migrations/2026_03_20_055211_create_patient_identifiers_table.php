<?php

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
        Schema::create('patient_identifiers', function (Blueprint $table) {
            $table->uuid('id')->unique()->primary();
            $table->foreignUuid('patient_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->string('value');
            $table->string('issuer')->nullable();
            $table->string('issuer_country')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->boolean('is_verified')->default(false);
            $table->dateTime('verified_at')->nullable();
            $table->unsignedBigInteger('verified_by')->nullable();
            $table->date('issue_date')->nullable();
            $table->date('expiry_date')->nullable();
            $table->text('note')->nullable();

            $table->unique(['patient_id', 'type','value']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('patient_identifiers');
    }
};
