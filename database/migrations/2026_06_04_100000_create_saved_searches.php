<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saved_searches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // Aktuell nur 'documents' — aber so kann das spaeter
            // auch fuer tasks/workflow-instances genutzt werden.
            $table->string('scope', 32)->default('documents');
            $table->string('name', 128);
            $table->json('params');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->index(['user_id', 'scope', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saved_searches');
    }
};
