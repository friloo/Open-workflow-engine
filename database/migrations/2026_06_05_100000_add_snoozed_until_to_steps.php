<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workflow_step_executions', function (Blueprint $table) {
            $table->timestamp('snoozed_until')->nullable()->after('due_at')->index();
        });
    }

    public function down(): void
    {
        Schema::table('workflow_step_executions', function (Blueprint $table) {
            $table->dropIndex(['snoozed_until']);
            $table->dropColumn('snoozed_until');
        });
    }
};
