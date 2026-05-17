<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflows', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->enum('status', ['draft', 'active', 'archived'])->default('draft');
            $table->enum('trigger_type', ['form', 'manual', 'schedule', 'recurring'])->default('manual');
            $table->boolean('is_public')->default(false);
            $table->string('public_slug')->nullable()->unique();
            $table->foreignId('current_version_id')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('workflow_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version_number');
            $table->json('definition');
            $table->json('form_schema')->nullable();
            $table->text('change_summary')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->index();

            $table->unique(['workflow_id', 'version_number']);
        });

        Schema::table('workflows', function (Blueprint $table) {
            $table->foreign('current_version_id')->references('id')->on('workflow_versions')->nullOnDelete();
        });

        Schema::create('workflow_instances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_id')->constrained()->restrictOnDelete();
            $table->foreignId('workflow_version_id')->constrained('workflow_versions')->restrictOnDelete();
            $table->foreignId('started_by')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('status', ['running', 'completed', 'cancelled', 'failed'])->default('running')->index();
            $table->string('current_step_key')->nullable();
            $table->json('data')->nullable();
            $table->timestamp('started_at')->index();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('workflow_step_executions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_instance_id')->constrained()->cascadeOnDelete();
            $table->string('step_key');
            $table->string('step_type', 64);
            $table->foreignId('assigned_to_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('assigned_to_role_id')->nullable()->constrained('roles')->nullOnDelete();
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('due_at')->nullable()->index();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('decision', 32)->nullable();
            $table->text('comment')->nullable();
            $table->json('data_snapshot')->nullable();
            $table->foreignId('escalated_from_step_id')->nullable()->constrained('workflow_step_executions')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_step_executions');
        Schema::dropIfExists('workflow_instances');
        Schema::table('workflows', function (Blueprint $table) {
            $table->dropForeign(['current_version_id']);
        });
        Schema::dropIfExists('workflow_versions');
        Schema::dropIfExists('workflows');
    }
};
