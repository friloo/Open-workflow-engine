<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tags', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug', 64)->unique();
            $table->string('color', 16)->default('#64748b');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('attachment_tag', function (Blueprint $table) {
            $table->foreignId('attachment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained()->cascadeOnDelete();
            $table->primary(['attachment_id', 'tag_id']);
        });

        Schema::create('document_cases', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('reference', 128)->nullable();           // z.B. Kunden-Nr, Vertrag-Nr
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('attachment_document_case', function (Blueprint $table) {
            $table->foreignId('attachment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('document_case_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->primary(['attachment_id', 'document_case_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attachment_document_case');
        Schema::dropIfExists('document_cases');
        Schema::dropIfExists('attachment_tag');
        Schema::dropIfExists('tags');
    }
};
