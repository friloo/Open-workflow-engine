<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('folder_inboxes', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('path');                            // absoluter Pfad ODER storage-Pfad
            $table->boolean('use_storage_disk')->default(false); // wenn true: $path ist relativ zu storage_path('app')
            $table->string('document_type', 64)->nullable();
            $table->foreignId('workflow_id')->nullable()->constrained()->nullOnDelete();
            $table->string('after_import')->default('delete');  // delete | move
            $table->string('processed_subfolder')->default('verarbeitet');
            $table->json('extensions')->nullable();             // ['pdf','png','jpg'] — null = alle erlaubten
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_scan_at')->nullable();
            $table->string('last_status')->nullable();
            $table->text('last_error')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('folder_inboxes');
    }
};
