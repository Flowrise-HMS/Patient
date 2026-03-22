<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('emergency_contacts', function (Blueprint $table) {
            $table->string('phone', 500)->nullable()->change();
            $table->string('alternate_phone', 500)->nullable()->change();
            $table->string('email', 500)->nullable()->change();
            $table->string('address', 500)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('emergency_contacts', function (Blueprint $table) {
            $table->string('phone', 255)->nullable()->change();
            $table->string('alternate_phone', 255)->nullable()->change();
            $table->string('email', 255)->nullable()->change();
            $table->string('address', 255)->nullable()->change();
        });
    }
};
