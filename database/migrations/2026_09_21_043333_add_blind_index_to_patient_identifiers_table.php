<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('patient_identifiers', function (Blueprint $table): void {
            $table->string('value_index', 64)->nullable()->after('value');
            $table->index(['type', 'value_index']);
        });
    }

    public function down(): void
    {
        Schema::table('patient_identifiers', function (Blueprint $table): void {
            $table->dropIndex(['type', 'value_index']);
            $table->dropColumn('value_index');
        });
    }
};
