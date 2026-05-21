<?php

namespace Tests\Feature;

use App\Models\AppNotification;
use App\Models\Contract;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ContractsQuarterlyReviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_review_sends_in_app_notification_per_owner(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        Mail::fake();
        $owner = User::factory()->create();
        $owner->assignRole('admin');

        Contract::create(['name' => 'A', 'notice_period_days' => 90, 'status' => 'active', 'owner_user_id' => $owner->id, 'created_by' => $owner->id]);
        Contract::create(['name' => 'B', 'notice_period_days' => 90, 'status' => 'active', 'owner_user_id' => $owner->id, 'created_by' => $owner->id]);

        Artisan::call('contracts:quarterly-review');

        $n = AppNotification::where('user_id', $owner->id)->where('type', 'contract.quarterly_review')->first();
        $this->assertNotNull($n);
        $this->assertStringContainsString('2 Verträge', $n->title);
    }

    public function test_review_skips_inactive_owners(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        Mail::fake();
        $owner = User::factory()->create(['is_active' => false]);
        Contract::create(['name' => 'A', 'notice_period_days' => 90, 'status' => 'active', 'owner_user_id' => $owner->id]);

        Artisan::call('contracts:quarterly-review');

        $this->assertSame(0, AppNotification::where('user_id', $owner->id)->count());
    }

    public function test_dry_run_does_not_send(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $owner = User::factory()->create();
        Contract::create(['name' => 'A', 'notice_period_days' => 90, 'status' => 'active', 'owner_user_id' => $owner->id]);

        Artisan::call('contracts:quarterly-review', ['--dry-run' => true]);
        $this->assertSame(0, AppNotification::count());
    }
}
