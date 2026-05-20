<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'ldap_dn')) {
                $table->string('ldap_dn', 512)->nullable();
                $table->index('ldap_dn');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'ldap_dn')) {
                $table->dropIndex(['ldap_dn']);
                $table->dropColumn('ldap_dn');
            }
        });
    }
};
