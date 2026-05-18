<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assets', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type')->index();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('valid_until')->nullable()->index();
            $table->timestamp('last_review_at')->nullable();
            $table->foreignId('workflow_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('lead_time_days')->default(30);
            $table->enum('status', ['active', 'expired', 'archived'])->default('active')->index();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assets');
    }
};
