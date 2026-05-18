<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // 2FA pro Benutzer (opt-in)
        Schema::table('users', function (Blueprint $table) {
            $table->text('two_factor_secret_enc')->nullable();
            $table->text('two_factor_recovery_codes_enc')->nullable();
            $table->timestamp('two_factor_confirmed_at')->nullable();
        });

        // Persoenliche API-Tokens
        Schema::create('api_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('token_hash', 64)->unique();   // SHA-256 des Klartext-Tokens
            $table->string('prefix', 8);                  // erste 8 Zeichen zum Anzeigen ("owe_abc1")
            $table->json('abilities')->nullable();        // ['workflows.run', 'forms.view', ...]
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
        });

        // In-App-Benachrichtigungen (Glocke)
        Schema::create('app_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 64);                   // task.assigned, share.review_due, mailbox.error, ...
            $table->string('title');
            $table->string('body', 1024)->nullable();
            $table->string('url')->nullable();            // Tiefenlink (z.B. /tasks/123)
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'read_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_notifications');
        Schema::dropIfExists('api_tokens');
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['two_factor_secret_enc', 'two_factor_recovery_codes_enc', 'two_factor_confirmed_at']);
        });
    }
};
