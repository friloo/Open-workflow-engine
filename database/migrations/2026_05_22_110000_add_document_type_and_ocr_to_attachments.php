<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attachments', function (Blueprint $table) {
            $table->string('document_type', 64)->nullable()->index()->after('label');
            $table->enum('ocr_status', ['pending', 'done', 'failed', 'skipped'])->default('pending')->index()->after('document_type');
            $table->longText('ocr_text')->nullable()->after('ocr_status');
            $table->timestamp('ocr_extracted_at')->nullable()->after('ocr_text');
            $table->string('ocr_tool', 32)->nullable()->after('ocr_extracted_at');
        });
    }

    public function down(): void
    {
        Schema::table('attachments', function (Blueprint $table) {
            $table->dropColumn(['document_type', 'ocr_status', 'ocr_text', 'ocr_extracted_at', 'ocr_tool']);
        });
    }
};
