<?php

use App\Models\Contract;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contract_types', function (Blueprint $table) {
            $table->id();
            $table->string('name', 128);
            $table->string('slug', 64)->unique();
            $table->string('color', 16)->default('#64748b');
            $table->unsignedSmallInteger('default_notice_period_days')->default(90);
            $table->text('description')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // Welche Rollen dürfen Verträge dieses Typs sehen?
        Schema::create('contract_type_role', function (Blueprint $table) {
            $table->foreignId('contract_type_id')->constrained()->cascadeOnDelete();
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->boolean('can_manage')->default(false); // wenn true: Bearbeiten erlaubt
            $table->timestamps();
            $table->primary(['contract_type_id', 'role_id'], 'ct_role_pk');
        });

        // Pro einzelnem Vertrag: zusaetzliche Rollen die Zugriff bekommen.
        // Owner und die Typ-Berechtigten haben sowieso Zugriff.
        Schema::create('contract_role', function (Blueprint $table) {
            $table->foreignId('contract_id')->constrained()->cascadeOnDelete();
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->boolean('can_manage')->default(false);
            $table->timestamps();
            $table->primary(['contract_id', 'role_id'], 'c_role_pk');
        });

        Schema::table('contracts', function (Blueprint $table) {
            if (! Schema::hasColumn('contracts', 'contract_type_id')) {
                $table->foreignId('contract_type_id')->nullable()
                    ->after('id')
                    ->constrained('contract_types')->nullOnDelete();
            }
        });

        // Bestehende Verträge: aus dem Freitext-Feld "category" einen Typ
        // erzeugen, damit die alten Daten in der neuen Struktur landen.
        if (Schema::hasTable('contracts')) {
            $categories = DB::table('contracts')
                ->whereNotNull('category')
                ->where('category', '!=', '')
                ->distinct()->pluck('category');
            foreach ($categories as $cat) {
                $slug = Str::slug((string) $cat) ?: 'sonstiges';
                $typeId = DB::table('contract_types')->insertGetId([
                    'name' => $cat,
                    'slug' => $slug,
                    'color' => '#64748b',
                    'default_notice_period_days' => 90,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                DB::table('contracts')->where('category', $cat)->update(['contract_type_id' => $typeId]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            if (Schema::hasColumn('contracts', 'contract_type_id')) {
                $table->dropForeign(['contract_type_id']);
                $table->dropColumn('contract_type_id');
            }
        });
        Schema::dropIfExists('contract_role');
        Schema::dropIfExists('contract_type_role');
        Schema::dropIfExists('contract_types');
    }
};
