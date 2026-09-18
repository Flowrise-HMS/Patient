<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->foreignUuid('merged_into_patient_id')
                ->nullable()
                ->after('is_deceased')
                ->constrained('patients')
                ->nullOnDelete();
            $table->timestamp('merged_at')->nullable()->after('merged_into_patient_id');
            $table->unsignedBigInteger('merged_by')->nullable()->after('merged_at');
        });
    }

    public function down(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->dropConstrainedForeignId('merged_into_patient_id');
            $table->dropColumn(['merged_at', 'merged_by']);
        });
    }
};
