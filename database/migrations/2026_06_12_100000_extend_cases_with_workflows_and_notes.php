<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('case_workflow_instance')) {
            Schema::create('case_workflow_instance', function (Blueprint $table) {
                $table->foreignId('document_case_id')->constrained()->cascadeOnDelete();
                $table->foreignId('workflow_instance_id')->constrained()->cascadeOnDelete();
                $table->timestamps();
                $table->primary(['document_case_id', 'workflow_instance_id'], 'case_wf_inst_pk');
            });
        }

        if (! Schema::hasTable('case_contract')) {
            Schema::create('case_contract', function (Blueprint $table) {
                $table->foreignId('document_case_id')->constrained()->cascadeOnDelete();
                $table->foreignId('contract_id')->constrained()->cascadeOnDelete();
                $table->timestamps();
                $table->primary(['document_case_id', 'contract_id']);
            });
        }

        if (! Schema::hasTable('case_notes')) {
            Schema::create('case_notes', function (Blueprint $table) {
                $table->id();
                $table->foreignId('document_case_id')->constrained()->cascadeOnDelete();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->text('body');
                $table->timestamps();
                $table->index(['document_case_id', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('case_notes');
        Schema::dropIfExists('case_contract');
        Schema::dropIfExists('case_workflow_instance');
    }
};
