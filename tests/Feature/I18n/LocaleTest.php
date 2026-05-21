<?php

namespace Tests\Feature\I18n;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocaleTest extends TestCase
{
    use RefreshDatabase;

    public function test_locale_defaults_to_de_for_anonymous(): void
    {
        $this->get(route('login'))->assertOk();
        $this->assertSame('de', app()->getLocale());
    }

    public function test_user_locale_overrides_default(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        config(['app.available_locales' => ['de' => 'Deutsch', 'en' => 'English']]);

        $user = User::factory()->create(['locale' => 'en']);
        $this->actingAs($user)->get(route('dashboard'))->assertOk();
        $this->assertSame('en', app()->getLocale());
    }

    public function test_unknown_locale_falls_back_to_default(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::factory()->create(['locale' => 'fr']); // nicht in available_locales

        $this->actingAs($user)->get(route('dashboard'))->assertOk();
        $this->assertSame('de', app()->getLocale());
    }

    public function test_profile_form_shows_picker_when_multiple_locales(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        config(['app.available_locales' => ['de' => 'Deutsch', 'en' => 'English']]);
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('profile.edit'))->assertOk()
            ->assertSee('Sprache / Language');
    }

    public function test_profile_form_hides_picker_when_single_locale(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        config(['app.available_locales' => ['de' => 'Deutsch']]);
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('profile.edit'))->assertOk()
            ->assertDontSee('Sprache / Language');
    }

    public function test_translation_loads_from_json_when_locale_is_active(): void
    {
        config(['app.available_locales' => ['de' => 'Deutsch', 'en' => 'English']]);
        app()->setLocale('de');

        $this->assertSame('Anmelden', __('Anmelden'));
    }
}
