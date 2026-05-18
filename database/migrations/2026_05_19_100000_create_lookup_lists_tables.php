<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lookup_lists', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->json('columns');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('lookup_list_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lookup_list_id')->constrained()->cascadeOnDelete();
            $table->string('key_value')->index();
            $table->json('data');
            $table->timestamps();

            $table->unique(['lookup_list_id', 'key_value']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lookup_list_entries');
        Schema::dropIfExists('lookup_lists');
    }
};
