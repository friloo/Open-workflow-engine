<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pdf_annotations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attachment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            // 'stamp' (vordefinierter Stempel-Text mit Farbe),
            // 'note'  (freitext-Anmerkung),
            // 'highlight' (vorgesehen fuer spaetere Visual-Overlay-Feature)
            $table->string('kind', 16)->default('note');
            $table->string('text', 500);
            // 'emerald','rose','amber','indigo','slate','violet','sky' — wir
            // mappen Farben auf Tailwind-Klassen im Frontend
            $table->string('color', 16)->default('slate');
            $table->unsignedSmallInteger('page')->nullable();
            $table->timestamps();
            $table->index(['attachment_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pdf_annotations');
    }
};
