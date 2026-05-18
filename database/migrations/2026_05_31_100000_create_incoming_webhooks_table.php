<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('incoming_webhooks', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('token', 64)->unique();        // Teil der URL
            $table->text('secret_enc')->nullable();        // optional HMAC-Secret
            $table->foreignId('workflow_id')->constrained()->cascadeOnDelete();
            $table->json('field_mappings')->nullable();    // [{"path":"data.email","field":"requester_email"}, ...]
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('last_called_at')->nullable();
            $table->unsignedInteger('call_count')->default(0);
            $table->unsignedInteger('failure_count')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incoming_webhooks');
    }
};
