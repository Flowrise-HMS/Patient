<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('emergency_contacts', function (Blueprint $table): void {
            $table->string('phone_index', 64)->nullable()->after('address')->index();
            $table->string('alternate_phone_index', 64)->nullable()->after('phone_index')->index();
            $table->string('email_index', 64)->nullable()->after('alternate_phone_index')->index();
        });
    }

    public function down(): void
    {
        Schema::table('emergency_contacts', function (Blueprint $table): void {
            $table->dropIndex(['phone_index']);
            $table->dropIndex(['alternate_phone_index']);
            $table->dropIndex(['email_index']);
            $table->dropColumn(['phone_index', 'alternate_phone_index', 'email_index']);
        });
    }
};
