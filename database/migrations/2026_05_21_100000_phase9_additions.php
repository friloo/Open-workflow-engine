<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->json('custom_fields')->nullable()->after('email_notifications_enabled');
        });

        Schema::create('workflow_instance_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_instance_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('body');
            $table->timestamp('created_at')->index();
        });

        Schema::create('webhooks', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('url');
            $table->json('events');
            $table->json('headers')->nullable();
            $table->text('secret')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_called_at')->nullable();
            $table->unsignedInteger('failure_count')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('workflow_step_executions', function (Blueprint $table) {
            $table->timestamp('reminder_sent_at')->nullable()->after('due_at');
        });
    }

    public function down(): void
    {
        Schema::table('workflow_step_executions', function (Blueprint $table) {
            $table->dropColumn('reminder_sent_at');
        });
        Schema::dropIfExists('webhooks');
        Schema::dropIfExists('workflow_instance_comments');
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('custom_fields');
        });
    }
};
