<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Keyed hashes of the encrypted phone/email so patients can be found by
 * exact phone number or email. Backfill with `php artisan patients:rebuild-search-indexes`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('patients', function (Blueprint $table): void {
            $table->string('phone_index', 64)->nullable()->after('email')->index();
            $table->string('email_index', 64)->nullable()->after('phone_index')->index();
        });
    }

    public function down(): void
    {
        Schema::table('patients', function (Blueprint $table): void {
            $table->dropIndex(['phone_index']);
            $table->dropIndex(['email_index']);
            $table->dropColumn(['phone_index', 'email_index']);
        });
    }
};
