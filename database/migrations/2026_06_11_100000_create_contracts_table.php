<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contracts', function (Blueprint $table) {
            $table->id();
            $table->string('name', 255);
            $table->string('party', 255)->nullable();   // Vertragspartner
            $table->string('category', 64)->nullable(); // z. B. Wartung, Miete, Versicherung
            $table->text('description')->nullable();

            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable()->index();
            // Bei laufzeit-gebundenen Vertraegen: Tage vor end_date erinnern
            $table->unsignedSmallInteger('notice_period_days')->default(90);
            $table->boolean('auto_renew')->default(false);
            $table->unsignedSmallInteger('auto_renew_months')->nullable();

            // Status wird per Cron gepflegt (active|notice_due|expired)
            $table->string('status', 32)->default('active')->index();

            // Verantwortlicher User (bekommt die Erinnerungen)
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();

            // Optional: Hauptdokument (Vertragsdatei). Genauere Verknuepfung
            // ueber attachments-Tabelle via attachable_type/id ist auch moeglich.
            $table->foreignId('attachment_id')->nullable()->constrained('attachments')->nullOnDelete();

            // Letzter Reminder, damit der Cron nicht jeden Tag erneut feuert
            $table->timestamp('last_reminder_at')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contracts');
    }
};
