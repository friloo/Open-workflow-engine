<?php

namespace Tests\Feature;

use App\Models\Form;
use App\Models\FormSubmission;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FormSubmissionViewTest extends TestCase
{
    use RefreshDatabase;

    private function designer(): User
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $u = User::factory()->create();
        $u->assignRole('workflow-designer');
        return $u;
    }

    public function test_designer_sees_submissions_list_and_data(): void
    {
        $u = $this->designer();
        $form = Form::create([
            'name' => 'Krankmeldung', 'slug' => 'krank', 'public_slug' => 'krank',
            'is_public' => true,
            'schema' => [
                ['key' => 'name', 'label' => 'Name', 'type' => 'text', 'required' => true],
                ['key' => 'tage', 'label' => 'Tage', 'type' => 'number', 'required' => true],
            ],
        ]);
        FormSubmission::create(['form_id' => $form->id, 'data' => ['name' => 'Max', 'tage' => 3]]);
        FormSubmission::create(['form_id' => $form->id, 'data' => ['name' => 'Erika', 'tage' => 5]]);

        $this->actingAs($u)->get(route('forms.submissions.index', $form))
            ->assertOk()
            ->assertSee('Max')
            ->assertSee('Erika');
    }

    public function test_csv_export_contains_schema_columns(): void
    {
        $u = $this->designer();
        $form = Form::create([
            'name' => 'Test', 'slug' => 'test', 'public_slug' => 'test', 'is_public' => true,
            'schema' => [
                ['key' => 'name', 'label' => 'Name', 'type' => 'text'],
                ['key' => 'okay', 'label' => 'Einverstanden', 'type' => 'checkbox'],
            ],
        ]);
        FormSubmission::create(['form_id' => $form->id, 'data' => ['name' => 'Max', 'okay' => true]]);

        $response = $this->actingAs($u)->get(route('forms.submissions.export', $form));
        $response->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $content = $response->streamedContent();
        $this->assertStringContainsString('Name;Einverstanden', $content);
        $this->assertStringContainsString('Max;ja', $content);
    }

    public function test_submission_detail_view(): void
    {
        $u = $this->designer();
        $form = Form::create([
            'name' => 'Test', 'slug' => 't', 'public_slug' => 't', 'is_public' => true,
            'schema' => [['key' => 'name', 'label' => 'Name', 'type' => 'text']],
        ]);
        $sub = FormSubmission::create(['form_id' => $form->id, 'data' => ['name' => 'Maxi']]);

        $this->actingAs($u)->get(route('forms.submissions.show', [$form, $sub]))
            ->assertOk()
            ->assertSee('Maxi');
    }
}
