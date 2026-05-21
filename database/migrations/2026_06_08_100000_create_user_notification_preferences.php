<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_notification_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // 'task.assigned', 'task.reminder', 'task.completed',
            // 'workflow.completed', 'workflow.failed', 'document.shared',
            // 'mention', ...
            $table->string('event_key', 64);
            // 'mail' | 'in_app'  (web_push später)
            $table->string('channel', 16);
            $table->boolean('enabled')->default(true);
            $table->timestamps();
            $table->unique(['user_id', 'event_key', 'channel']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_notification_preferences');
    }
};
