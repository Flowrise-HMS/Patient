<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE media MODIFY COLUMN model_id VARCHAR(36) NULL');
        DB::statement('ALTER TABLE media ADD INDEX idx_media_model (model_id, model_type)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE media MODIFY COLUMN model_id BIGINT UNSIGNED NULL');
        DB::statement('DROP INDEX idx_media_model ON media');
    }
};
