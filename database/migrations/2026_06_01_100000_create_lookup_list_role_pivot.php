<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('lookup_list_role', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lookup_list_id')->constrained()->cascadeOnDelete();
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->boolean('can_edit')->default(false);
            $table->timestamps();
            $table->unique(['lookup_list_id', 'role_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lookup_list_role');
    }
};
