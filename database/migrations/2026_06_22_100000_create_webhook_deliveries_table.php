<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhook_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('webhook_id')->constrained()->cascadeOnDelete();
            $table->string('event', 64)->index();
            $table->unsignedSmallInteger('response_code')->nullable();
            $table->boolean('ok')->default(false);
            $table->unsignedInteger('duration_ms')->default(0);
            $table->text('error')->nullable();
            $table->text('response_excerpt')->nullable(); // erste ~512 Zeichen
            $table->timestamp('sent_at')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_deliveries');
    }
};
