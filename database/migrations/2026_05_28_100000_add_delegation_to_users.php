<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('delegate_user_id')->nullable()->after('supervisor_id')->constrained('users')->nullOnDelete();
            $table->date('delegate_from')->nullable()->after('delegate_user_id');
            $table->date('delegate_to')->nullable()->after('delegate_from');
            $table->string('delegate_reason', 255)->nullable()->after('delegate_to');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('delegate_user_id');
            $table->dropColumn(['delegate_from', 'delegate_to', 'delegate_reason']);
        });
    }
};
