<?php

namespace Tests\Feature\Security;

use App\Models\User;
use App\Support\PasswordPolicy;
use App\Support\Settings;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class PasswordPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_policy_requires_at_least_8_chars(): void
    {
        $rules = PasswordPolicy::rules();
        $v = Validator::make(['password' => 'short1', 'password_confirmation' => 'short1'], ['password' => $rules]);
        $this->assertTrue($v->fails());
    }

    public function test_long_password_passes_default_policy(): void
    {
        $rules = PasswordPolicy::rules();
        $v = Validator::make([
            'password' => 'longenough', 'password_confirmation' => 'longenough',
        ], ['password' => $rules]);
        $this->assertFalse($v->fails());
    }

    public function test_uppercase_requirement_is_applied(): void
    {
        Settings::set('security.password.require_uppercase', true);
        $rules = PasswordPolicy::rules();
        $v = Validator::make([
            'password' => 'lowercaseonly1', 'password_confirmation' => 'lowercaseonly1',
        ], ['password' => $rules]);
        $this->assertTrue($v->fails());
    }

    public function test_symbol_requirement_is_applied(): void
    {
        Settings::set('security.password.require_symbol', true);
        $rules = PasswordPolicy::rules();
        $ok = Validator::make(['password' => 'AbcDef12!@', 'password_confirmation' => 'AbcDef12!@'], ['password' => $rules]);
        $fail = Validator::make(['password' => 'AbcDef1234', 'password_confirmation' => 'AbcDef1234'], ['password' => $rules]);
        $this->assertFalse($ok->fails());
        $this->assertTrue($fail->fails());
    }

    public function test_admin_can_update_policy_via_endpoint(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->actingAs($admin)->post(route('admin.settings.security.update'), [
            'min_length' => 12,
            'require_uppercase' => '1',
            'require_number' => '1',
            'max_age_days' => '180',
        ])->assertRedirect();

        $this->assertSame(12, (int) Settings::get('security.password.min_length'));
        $this->assertTrue((bool) Settings::get('security.password.require_uppercase'));
        $this->assertSame(180, (int) Settings::get('security.password.max_age_days'));
    }

    public function test_security_settings_view_renders(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->actingAs($admin)->get(route('admin.settings.security'))
            ->assertOk()->assertSee('Passwort-Policy');
    }
}
