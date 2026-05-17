<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_is_displayed(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/profile')->assertOk();
    }

    public function test_profile_information_can_be_updated(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->patch('/profile', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'email_notifications_enabled' => '0',
        ]);

        $response->assertSessionHasNoErrors()->assertRedirect('/profile');

        $user->refresh();
        $this->assertSame('Test User', $user->name);
        $this->assertSame('test@example.com', $user->email);
        $this->assertFalse($user->email_notifications_enabled);
    }

    public function test_user_can_toggle_email_notifications(): void
    {
        $user = User::factory()->create(['email_notifications_enabled' => true]);

        $this->actingAs($user)->patch('/profile', [
            'name' => $user->name,
            'email' => $user->email,
            'email_notifications_enabled' => '0',
        ])->assertRedirect('/profile');

        $this->assertFalse($user->fresh()->email_notifications_enabled);
    }
}
