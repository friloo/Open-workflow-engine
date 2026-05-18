<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('share_links', function (Blueprint $table) {
            $table->id();
            $table->string('token', 64)->unique();
            $table->foreignId('attachment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('follow_versions')->default(true);
            $table->string('password_hash')->nullable();
            $table->string('note', 255)->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->unsignedInteger('max_downloads')->nullable();
            $table->unsignedInteger('download_count')->default(0);
            $table->boolean('is_revoked')->default(false)->index();
            $table->timestamp('revoked_at')->nullable();
            $table->string('revoke_reason', 255)->nullable();
            $table->timestamp('last_review_sent_at')->nullable();
            $table->timestamp('last_review_response_at')->nullable();
            $table->text('review_response')->nullable();
            $table->timestamps();
        });

        Schema::create('share_link_accesses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('share_link_id')->constrained()->cascadeOnDelete();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->string('action', 32);
            $table->boolean('success')->default(true);
            $table->timestamp('accessed_at')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('share_link_accesses');
        Schema::dropIfExists('share_links');
    }
};
