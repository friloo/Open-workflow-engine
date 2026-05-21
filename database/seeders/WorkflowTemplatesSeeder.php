<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowVersion;
use Illuminate\Database\Seeder;

class WorkflowTemplatesSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::where('email', 'admin@example.com')->first();
        if (! $admin) return;

        $this->bestellantrag($admin);
        $this->krankmeldung($admin);
        $this->fuehrerschein($admin);
    }

    private function bestellantrag(User $admin): void
    {
        $wf = Workflow::firstOrCreate(
            ['slug' => 'vorlage-bestellantrag'],
            [
                'name' => 'Vorlage: Bestellantrag',
                'description' => 'Mitarbeiter beantragt Bestellung. Geht an Kostenstellen-Verantwortlichen (Liste), dann an Einkauf.',
                'trigger_type' => 'form',
                'status' => 'draft',
                
                'created_by' => $admin->id,
                'updated_by' => $admin->id,
            ]
        );
        if ($wf->current_version_id) return;

        $def = ['drawflow' => ['Home' => ['data' => [
            '1' => ['id'=>1,'name'=>'start','class'=>'start','data'=>['label'=>'Start'],'inputs'=>[],'outputs'=>['output_1'=>['connections'=>[['node'=>'2','output'=>'input_1']]]], 'pos_x'=>40,'pos_y'=>120],
            '2' => ['id'=>2,'name'=>'approval','class'=>'approval','data'=>[
                    'label'=>'Kostenstellen-Freigabe',
                    'recipient_type'=>'list_lookup','list_id'=>null,'lookup_source'=>'kostenstelle',
                    'grace_value'=>3,'grace_unit'=>'days',
                    'escalation_type'=>'list_lookup','allow_forward'=>true,
                ],
                'inputs'=>['input_1'=>[]],
                'outputs'=>['output_1'=>['connections'=>[['node'=>'3','output'=>'input_1']]],'output_2'=>['connections'=>[['node'=>'5','output'=>'input_1']]]],
                'pos_x'=>260,'pos_y'=>120],
            '3' => ['id'=>3,'name'=>'notify','class'=>'notify','data'=>[
                    'label'=>'Einkauf informieren',
                    'recipient_type'=>'role','recipient_role_id'=>null,
                    'subject'=>'Neue Bestellung freigegeben','body'=>'Bestellung von {{ initiator }} (Kostenstelle {{ kostenstelle }}) wurde freigegeben.',
                ],
                'inputs'=>['input_1'=>[]],'outputs'=>['output_1'=>['connections'=>[['node'=>'4','output'=>'input_1']]]],
                'pos_x'=>520,'pos_y'=>40],
            '4' => ['id'=>4,'name'=>'end','class'=>'end','data'=>['label'=>'Erledigt','result'=>'completed'],'inputs'=>['input_1'=>[]],'outputs'=>[], 'pos_x'=>740,'pos_y'=>60],
            '5' => ['id'=>5,'name'=>'end','class'=>'end','data'=>['label'=>'Abgelehnt','result'=>'rejected'],'inputs'=>['input_1'=>[]],'outputs'=>[], 'pos_x'=>520,'pos_y'=>200],
        ]]]];

        $form = [
            ['key'=>'kostenstelle','label'=>'Kostenstelle','type'=>'text','required'=>true,'options'=>[]],
            ['key'=>'beschreibung','label'=>'Was soll bestellt werden','type'=>'textarea','required'=>true,'options'=>[]],
            ['key'=>'betrag','label'=>'Geschätzter Betrag (EUR)','type'=>'number','required'=>true,'options'=>[]],
        ];

        $v = WorkflowVersion::create([
            'workflow_id' => $wf->id, 'version_number' => 1,
            'definition' => $def, 'form_schema' => $form,
            'change_summary' => 'Vorlage initial',
            'created_by' => $admin->id,
        ]);
        $wf->forceFill(['current_version_id' => $v->id])->save();
    }

    private function krankmeldung(User $admin): void
    {
        $wf = Workflow::firstOrCreate(
            ['slug' => 'vorlage-krankmeldung'],
            [
                'name' => 'Vorlage: Krankmeldung',
                'description' => 'Mitarbeiter meldet sich krank, Vorgesetzter wird benachrichtigt.',
                'trigger_type' => 'form',
                'status' => 'draft',
                
                'created_by' => $admin->id, 'updated_by' => $admin->id,
            ]
        );
        if ($wf->current_version_id) return;

        $def = ['drawflow' => ['Home' => ['data' => [
            '1' => ['id'=>1,'name'=>'start','class'=>'start','data'=>['label'=>'Start'],'inputs'=>[],'outputs'=>['output_1'=>['connections'=>[['node'=>'2','output'=>'input_1']]]],'pos_x'=>40,'pos_y'=>120],
            '2' => ['id'=>2,'name'=>'notify','class'=>'notify','data'=>[
                    'label'=>'Vorgesetzten informieren','recipient_type'=>'supervisor_of_initiator',
                    'subject'=>'Krankmeldung von {{ name }}','body'=>'{{ name }} ist ab heute für {{ tage }} Tage krank. Grund: {{ bemerkung }}',
                ],
                'inputs'=>['input_1'=>[]],'outputs'=>['output_1'=>['connections'=>[['node'=>'3','output'=>'input_1']]]],'pos_x'=>280,'pos_y'=>120],
            '3' => ['id'=>3,'name'=>'end','class'=>'end','data'=>['label'=>'Erledigt','result'=>'completed'],'inputs'=>['input_1'=>[]],'outputs'=>[],'pos_x'=>520,'pos_y'=>120],
        ]]]];
        $form = [
            ['key'=>'name','label'=>'Name','type'=>'text','required'=>true,'options'=>[]],
            ['key'=>'tage','label'=>'Voraussichtliche Krankheitstage','type'=>'number','required'=>true,'options'=>[]],
            ['key'=>'bemerkung','label'=>'Bemerkung (freiwillig)','type'=>'textarea','required'=>false,'options'=>[]],
        ];
        $v = WorkflowVersion::create([
            'workflow_id' => $wf->id, 'version_number' => 1,
            'definition' => $def, 'form_schema' => $form,
            'change_summary' => 'Vorlage initial', 'created_by' => $admin->id,
        ]);
        $wf->forceFill(['current_version_id' => $v->id])->save();
    }

    private function fuehrerschein(User $admin): void
    {
        $wf = Workflow::firstOrCreate(
            ['slug' => 'vorlage-fuehrerschein-pruefung'],
            [
                'name' => 'Vorlage: Führerschein-Prüfung',
                'description' => 'Wiederkehrende Prüfung. Vorgesetzter bestätigt Sichtung des Führerscheins.',
                'trigger_type' => 'recurring',
                'status' => 'draft',
                'created_by' => $admin->id, 'updated_by' => $admin->id,
            ]
        );
        if ($wf->current_version_id) return;

        $def = ['drawflow' => ['Home' => ['data' => [
            '1' => ['id'=>1,'name'=>'start','class'=>'start','data'=>['label'=>'Start'],'inputs'=>[],'outputs'=>['output_1'=>['connections'=>[['node'=>'2','output'=>'input_1']]]],'pos_x'=>40,'pos_y'=>120],
            '2' => ['id'=>2,'name'=>'approval','class'=>'approval','data'=>[
                    'label'=>'Sichtung durch Vorgesetzten',
                    'recipient_type'=>'supervisor_of_initiator',
                    'grace_value'=>14,'grace_unit'=>'days',
                    'escalation_type'=>'role','escalation_role_id'=>null,
                ],
                'inputs'=>['input_1'=>[]],
                'outputs'=>['output_1'=>['connections'=>[['node'=>'3','output'=>'input_1']]],'output_2'=>['connections'=>[['node'=>'4','output'=>'input_1']]]],
                'pos_x'=>260,'pos_y'=>120],
            '3' => ['id'=>3,'name'=>'end','class'=>'end','data'=>['label'=>'Gesichtet','result'=>'completed'],'inputs'=>['input_1'=>[]],'outputs'=>[],'pos_x'=>520,'pos_y'=>40],
            '4' => ['id'=>4,'name'=>'end','class'=>'end','data'=>['label'=>'Beanstandet','result'=>'rejected'],'inputs'=>['input_1'=>[]],'outputs'=>[],'pos_x'=>520,'pos_y'=>200],
        ]]]];
        $v = WorkflowVersion::create([
            'workflow_id' => $wf->id, 'version_number' => 1,
            'definition' => $def, 'form_schema' => null,
            'change_summary' => 'Vorlage initial', 'created_by' => $admin->id,
        ]);
        $wf->forceFill(['current_version_id' => $v->id])->save();
    }
}
