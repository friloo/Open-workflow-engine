<?php

namespace Tests\Feature;

use App\Models\Attachment;
use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowInstance;
use App\Models\WorkflowStepExecution;
use App\Models\WorkflowVersion;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class ApprovalExtraFieldsTest extends TestCase
{
    use RefreshDatabase;

    public function test_zusatzfeld_landet_als_indexed_field_am_doku(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('employee');

        Storage::fake('local');

        // Workflow mit einem Approval-Knoten, der ein Zusatzfeld 'bemerkung'
        // mit target='doc' definiert.
        $definition = ['drawflow' => ['Home' => ['data' => [
            'node-1' => [
                'class' => 'approval',
                'data' => [
                    'label' => 'Test',
                    'recipient_type' => 'user',
                    'extra_fields' => [[
                        'key' => 'bemerkung',
                        'label' => 'Bemerkung',
                        'type' => 'text',
                        'required' => false,
                        'target' => 'doc',
                    ]],
                ],
                'outputs' => ['output_1' => ['connections' => []], 'output_2' => ['connections' => []]],
            ],
        ]]]];
        $workflow = Workflow::create(['name' => 'T', 'slug' => 't', 'status' => 'active', 'created_by' => $user->id]);
        $version = WorkflowVersion::create([
            'workflow_id' => $workflow->id, 'version_number' => 1, 'definition' => $definition,
        ]);
        $instance = WorkflowInstance::create([
            'workflow_id' => $workflow->id, 'workflow_version_id' => $version->id,
            'data' => [], 'status' => 'running',
            'started_at' => now(), 'started_by' => $user->id,
        ]);

        // Anhang vorhanden, OHNE Indexfelder.
        $att = Attachment::create([
            'disk' => 'local', 'path' => 'docs/r.pdf',
            'original_name' => 'r.pdf', 'mime_type' => 'application/pdf',
            'size' => 100, 'content_hash' => str_repeat('c', 64),
            'uploaded_by' => $user->id, 'is_current_version' => true,
            'version_chain_id' => Str::uuid(), 'version_number' => 1,
            'attachable_type' => WorkflowInstance::class,
            'attachable_id' => $instance->id,
        ]);

        $step = WorkflowStepExecution::create([
            'workflow_instance_id' => $instance->id,
            'step_key' => 'node-1', 'step_type' => 'approval',
            'assigned_to_user_id' => $user->id,
        ]);

        $this->actingAs($user)->post(route('tasks.decide', $step), [
            'decision' => 'approved',
            'comment' => '',
            'extra' => ['bemerkung' => 'Geprueft, ok.'],
        ])->assertRedirect();

        $att->refresh();
        $this->assertIsArray($att->indexed_fields);
        $this->assertSame('Geprueft, ok.', $att->indexed_fields['bemerkung'] ?? null);
        $this->assertNotNull($att->indexed_at);
    }

    public function test_zusatzfeld_wird_ins_doku_schema_uebernommen(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        \App\Support\Settings::set('attachments.document_types', ['Rechnung']);
        \App\Support\Settings::set('attachments.field_schemas', [
            'Rechnung' => [['key' => 'rechnungsnummer', 'label' => 'Rechnungsnr.', 'type' => 'string', 'extractor' => 'manual']],
        ]);
        $user = User::factory()->create();
        $user->assignRole('employee');

        Storage::fake('local');

        $definition = ['drawflow' => ['Home' => ['data' => [
            'node-1' => [
                'class' => 'approval',
                'data' => [
                    'label' => 'Pruefen',
                    'extra_fields' => [[
                        'key' => 'bemerkung', 'label' => 'Bemerkung', 'type' => 'textarea',
                        'required' => false, 'target' => 'doc',
                    ]],
                ],
                'outputs' => ['output_1' => ['connections' => []], 'output_2' => ['connections' => []]],
            ],
        ]]]];
        $workflow = Workflow::create(['name' => 'TR', 'slug' => 'tr', 'status' => 'active', 'created_by' => $user->id]);
        $version = WorkflowVersion::create(['workflow_id' => $workflow->id, 'version_number' => 1, 'definition' => $definition]);
        $instance = WorkflowInstance::create([
            'workflow_id' => $workflow->id, 'workflow_version_id' => $version->id,
            'data' => [], 'status' => 'running', 'started_at' => now(), 'started_by' => $user->id,
        ]);

        Attachment::create([
            'disk' => 'local', 'path' => 'docs/r.pdf',
            'original_name' => 'r.pdf', 'mime_type' => 'application/pdf',
            'size' => 100, 'content_hash' => str_repeat('e', 64),
            'uploaded_by' => $user->id, 'is_current_version' => true,
            'version_chain_id' => Str::uuid(), 'version_number' => 1,
            'attachable_type' => WorkflowInstance::class,
            'attachable_id' => $instance->id,
            'document_type' => 'Rechnung',
        ]);
        $step = WorkflowStepExecution::create([
            'workflow_instance_id' => $instance->id, 'step_key' => 'node-1',
            'step_type' => 'approval', 'assigned_to_user_id' => $user->id,
        ]);

        $this->actingAs($user)->post(route('tasks.decide', $step), [
            'decision' => 'approved',
            'extra' => ['bemerkung' => 'sieht gut aus'],
        ])->assertRedirect();

        $schema = \App\Support\DocumentFieldSchema::forType('Rechnung');
        $keys = collect($schema)->pluck('key')->all();
        $this->assertContains('rechnungsnummer', $keys, 'Bestehendes Feld darf nicht weg sein');
        $this->assertContains('bemerkung', $keys, 'Neues Feld muss ergaenzt sein');

        // 2. Lauf: kein erneutes Anhaengen (Idempotenz)
        $instance2 = WorkflowInstance::create([
            'workflow_id' => $workflow->id, 'workflow_version_id' => $version->id,
            'data' => [], 'status' => 'running', 'started_at' => now(), 'started_by' => $user->id,
        ]);
        Attachment::create([
            'disk' => 'local', 'path' => 'docs/r2.pdf',
            'original_name' => 'r2.pdf', 'mime_type' => 'application/pdf',
            'size' => 100, 'content_hash' => str_repeat('f', 64),
            'uploaded_by' => $user->id, 'is_current_version' => true,
            'version_chain_id' => Str::uuid(), 'version_number' => 1,
            'attachable_type' => WorkflowInstance::class,
            'attachable_id' => $instance2->id, 'document_type' => 'Rechnung',
        ]);
        $step2 = WorkflowStepExecution::create([
            'workflow_instance_id' => $instance2->id, 'step_key' => 'node-1',
            'step_type' => 'approval', 'assigned_to_user_id' => $user->id,
        ]);
        $this->actingAs($user)->post(route('tasks.decide', $step2), [
            'decision' => 'approved',
            'extra' => ['bemerkung' => 'auch ok'],
        ])->assertRedirect();

        $schema2 = \App\Support\DocumentFieldSchema::forType('Rechnung');
        $this->assertCount(2, $schema2, 'Schema soll bei wiederholtem Lauf nicht waeschsen');
    }

    public function test_required_zusatzfeld_blockt_submit_ohne_wert(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('employee');

        $definition = ['drawflow' => ['Home' => ['data' => [
            'node-1' => [
                'class' => 'approval',
                'data' => [
                    'label' => 'Pruef-Approval',
                    'extra_fields' => [[
                        'key' => 'pruefnr', 'label' => 'Pruefnummer', 'type' => 'text',
                        'required' => true, 'target' => 'doc',
                    ]],
                ],
                'outputs' => ['output_1' => ['connections' => []], 'output_2' => ['connections' => []]],
            ],
        ]]]];
        $workflow = Workflow::create(['name' => 'T2', 'slug' => 't2', 'status' => 'active', 'created_by' => $user->id]);
        $version = WorkflowVersion::create(['workflow_id' => $workflow->id, 'version_number' => 1, 'definition' => $definition]);
        $instance = WorkflowInstance::create([
            'workflow_id' => $workflow->id, 'workflow_version_id' => $version->id,
            'data' => [], 'status' => 'running', 'started_at' => now(), 'started_by' => $user->id,
        ]);
        $step = WorkflowStepExecution::create([
            'workflow_instance_id' => $instance->id, 'step_key' => 'node-1',
            'step_type' => 'approval', 'assigned_to_user_id' => $user->id,
        ]);

        $this->actingAs($user)->post(route('tasks.decide', $step), [
            'decision' => 'approved',
            'extra' => ['pruefnr' => ''], // leer obwohl required
        ])->assertSessionHasErrors('extra.pruefnr');
    }

    public function test_zusatzfeld_mit_target_instance_landet_in_workflow_data(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('employee');

        $definition = ['drawflow' => ['Home' => ['data' => [
            'node-1' => [
                'class' => 'approval',
                'data' => [
                    'label' => 'Risiko-Einstufung',
                    'extra_fields' => [[
                        'key' => 'risiko_eingestuft', 'label' => 'Risiko', 'type' => 'select',
                        'options' => ['niedrig', 'mittel', 'hoch'],
                        'required' => false, 'target' => 'instance',
                    ]],
                ],
                'outputs' => ['output_1' => ['connections' => []], 'output_2' => ['connections' => []]],
            ],
        ]]]];
        $workflow = Workflow::create(['name' => 'T3', 'slug' => 't3', 'status' => 'active', 'created_by' => $user->id]);
        $version = WorkflowVersion::create(['workflow_id' => $workflow->id, 'version_number' => 1, 'definition' => $definition]);
        $instance = WorkflowInstance::create([
            'workflow_id' => $workflow->id, 'workflow_version_id' => $version->id,
            'data' => [], 'status' => 'running', 'started_at' => now(), 'started_by' => $user->id,
        ]);
        $step = WorkflowStepExecution::create([
            'workflow_instance_id' => $instance->id, 'step_key' => 'node-1',
            'step_type' => 'approval', 'assigned_to_user_id' => $user->id,
        ]);

        $this->actingAs($user)->post(route('tasks.decide', $step), [
            'decision' => 'approved',
            'extra' => ['risiko_eingestuft' => 'hoch'],
        ])->assertRedirect();

        $instance->refresh();
        $this->assertSame('hoch', $instance->data['risiko_eingestuft'] ?? null);
        $this->assertSame('hoch', $instance->data['_approval']['node-1']['risiko_eingestuft'] ?? null);
    }
}
