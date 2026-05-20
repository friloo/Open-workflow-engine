<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'oidc_subject')) {
                $table->string('oidc_subject')->nullable()->unique();
            }
            if (! Schema::hasColumn('users', 'google_subject')) {
                $table->string('google_subject')->nullable()->unique();
            }
            if (! Schema::hasColumn('users', 'saml_nameid')) {
                $table->string('saml_nameid')->nullable()->unique();
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            foreach (['oidc_subject', 'google_subject', 'saml_nameid'] as $col) {
                if (Schema::hasColumn('users', $col)) {
                    $table->dropUnique([$col]);
                    $table->dropColumn($col);
                }
            }
        });
    }
};
