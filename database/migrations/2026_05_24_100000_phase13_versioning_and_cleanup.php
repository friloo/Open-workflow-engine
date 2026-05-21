<?php

use App\Models\Workflow;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        // 1) Dokumenten-Versionierung auf attachments
        Schema::table('attachments', function (Blueprint $table) {
            $table->char('version_chain_id', 36)->nullable()->index()->after('content_hash');
            $table->unsignedSmallInteger('version_number')->default(1)->after('version_chain_id');
            $table->boolean('is_current_version')->default(true)->index()->after('version_number');
        });

        // Bestehende Anhänge bekommen je eine eigene Chain (Version 1, current=true)
        foreach (DB::table('attachments')->whereNull('version_chain_id')->cursor() as $row) {
            DB::table('attachments')->where('id', $row->id)->update([
                'version_chain_id' => (string) Str::uuid(),
                'version_number' => 1,
                'is_current_version' => true,
            ]);
        }

        // 2) Orphan-Dokumente erlauben (kein Parent-Objekt nötig)
        // SQLite kann morphs nicht ohne weiteres ändern — auf MySQL ist es safe.
        if (DB::connection()->getDriverName() !== 'sqlite') {
            Schema::table('attachments', function (Blueprint $table) {
                $table->string('attachable_type')->nullable()->change();
                $table->unsignedBigInteger('attachable_id')->nullable()->change();
            });
        }

        // 3) workflows.is_public -> als Form abbilden
        $oldPublics = DB::table('workflows')
            ->where('is_public', true)
            ->whereNotNull('public_slug')
            ->get();
        foreach ($oldPublics as $w) {
            if (! Schema::hasTable('forms')) continue;
            $exists = DB::table('forms')->where('public_slug', $w->public_slug)->exists();
            if ($exists) continue;
            DB::table('forms')->insert([
                'name' => $w->name,
                'slug' => 'wf-'.$w->slug,
                'description' => $w->description,
                'schema' => DB::table('workflow_versions')->where('id', $w->current_version_id)->value('form_schema') ?: json_encode([]),
                'is_public' => true,
                'public_slug' => $w->public_slug,
                'workflow_id' => $w->id,
                'created_by' => $w->created_by,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        Schema::table('workflows', function (Blueprint $table) {
            // Index muss separat gedropped werden, sonst meckert SQLite.
            try { $table->dropUnique('workflows_public_slug_unique'); } catch (\Throwable) {}
        });
        Schema::table('workflows', function (Blueprint $table) {
            $table->dropColumn(['is_public', 'public_slug']);
        });

        // 4) trigger_type=schedule entfernen — übrige Einträge auf recurring umstellen.
        DB::table('workflows')->where('trigger_type', 'schedule')->update(['trigger_type' => 'recurring']);
    }

    public function down(): void
    {
        Schema::table('workflows', function (Blueprint $table) {
            $table->boolean('is_public')->default(false);
            $table->string('public_slug')->nullable()->unique();
        });

        Schema::table('attachments', function (Blueprint $table) {
            $table->dropColumn(['version_chain_id', 'version_number', 'is_current_version']);
        });
    }
};
