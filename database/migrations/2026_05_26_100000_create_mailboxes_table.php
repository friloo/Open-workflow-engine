<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('mailboxes', function (Blueprint $table) {
            $table->id();
            $table->string('name');                           // Anzeigename
            $table->string('host');
            $table->unsignedSmallInteger('port')->default(993);
            $table->string('encryption', 16)->default('ssl'); // ssl|tls|none
            $table->boolean('validate_cert')->default(true);
            $table->string('username');
            $table->text('password_enc');                     // Crypt::encryptString
            $table->string('folder')->default('INBOX');
            $table->string('document_type', 64)->nullable();
            $table->foreignId('workflow_id')->nullable()->constrained()->nullOnDelete();
            $table->string('subject_field')->nullable();      // optional Mail-Felder ins Workflow-Formular
            $table->string('from_field')->nullable();
            $table->string('body_field')->nullable();
            $table->boolean('ai_classify')->default(false);
            $table->boolean('move_processed')->default(true);
            $table->string('processed_folder')->default('Verarbeitet');
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_fetch_at')->nullable();
            $table->string('last_status')->nullable();
            $table->text('last_error')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('mailbox_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mailbox_id')->constrained()->cascadeOnDelete();
            $table->string('uid')->index();              // IMAP-UID des Servers
            $table->string('message_id')->nullable();
            $table->string('from_email')->nullable();
            $table->string('from_name')->nullable();
            $table->string('subject')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->foreignId('workflow_instance_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedTinyInteger('attachment_count')->default(0);
            $table->string('status', 16)->default('processed'); // processed|skipped|failed
            $table->text('error')->nullable();
            $table->timestamps();
            $table->unique(['mailbox_id', 'uid']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mailbox_messages');
        Schema::dropIfExists('mailboxes');
    }
};
