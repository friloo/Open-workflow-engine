<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflow_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subject_user_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->string('subject_label')->nullable();
            $table->json('payload')->nullable();
            $table->unsignedInteger('interval_value');
            $table->enum('interval_unit', ['days', 'weeks', 'months', 'years']);
            $table->timestamp('next_run_at')->index();
            $table->timestamp('last_run_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_schedules');
    }
};
