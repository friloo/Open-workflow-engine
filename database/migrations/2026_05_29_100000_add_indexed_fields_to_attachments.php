<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('attachments', function (Blueprint $table) {
            $table->json('indexed_fields')->nullable()->after('ocr_text');
            $table->timestamp('indexed_at')->nullable()->after('indexed_fields');
        });
    }

    public function down(): void
    {
        Schema::table('attachments', function (Blueprint $table) {
            $table->dropColumn(['indexed_fields', 'indexed_at']);
        });
    }
};
