<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('secrets', function (Blueprint $table) {
            $table->id();
            $table->string('key', 64)->unique();
            $table->text('value');
            $table->string('description', 255)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('attachments', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('attachments', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
        Schema::dropIfExists('secrets');
    }
};
