<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workflow_instances', function (Blueprint $table) {
            // Parent-Step der die child-Instance gestartet hat (nullable).
            // Wenn die Child-Instance completed, weckt der Engine den
            // parent step wieder auf und macht weiter.
            $table->foreignId('parent_step_execution_id')
                ->nullable()->after('current_step_key')
                ->index();
        });

        Schema::table('workflow_step_executions', function (Blueprint $table) {
            // Für For-each-Loops: Anzahl gestarteter Sub-Instances und
            // wieviele davon schon fertig sind. Wenn children_completed
            // == children_count, ist der Loop fertig.
            $table->unsignedInteger('children_count')->nullable()->after('data_snapshot');
            $table->unsignedInteger('children_completed_count')->nullable()->after('children_count');
        });
    }

    public function down(): void
    {
        Schema::table('workflow_instances', function (Blueprint $table) {
            $table->dropIndex(['parent_step_execution_id']);
            $table->dropColumn('parent_step_execution_id');
        });
        Schema::table('workflow_step_executions', function (Blueprint $table) {
            $table->dropColumn(['children_count', 'children_completed_count']);
        });
    }
};
