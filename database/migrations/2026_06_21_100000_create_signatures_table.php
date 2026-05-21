<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('signatures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attachment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('workflow_step_execution_id')->nullable()->constrained()->nullOnDelete();
            $table->string('level', 8)->index(); // ses | aes | qes
            $table->string('provider', 64)->nullable(); // 'internal' | 'd-trust' | ...
            $table->string('content_hash', 64); // sha256 des Dokuments zum Sign-Zeitpunkt
            $table->string('signer_name', 255);
            $table->string('signer_email', 255)->nullable();
            $table->string('signer_ip', 45)->nullable();
            $table->text('certificate_pem')->nullable(); // PEM-encoded X.509 (AES/QES)
            $table->longText('signature_blob')->nullable(); // Base64 PKCS#7 / detached signature
            $table->boolean('twofa_verified')->default(false);
            $table->timestamp('signed_at')->useCurrent();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['attachment_id', 'level']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('signatures');
    }
};
