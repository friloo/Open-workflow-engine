<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\Attachment;
use App\Models\ShareLink;
use App\Models\ShareLinkAccess;
use App\Models\User;
use App\Services\AttachmentStorage;
use App\Support\Settings;
use Carbon\Carbon;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class ShareLinkTest extends TestCase
{
    use RefreshDatabase;

    private function setupAttachment(): array
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        Storage::fake('local');
        Settings::set('attachments.document_types', ['Vertrag']);
        Settings::set('attachments.role_document_types', ['admin' => ['Vertrag']]);

        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $asset = Asset::create(['name' => 'X', 'type' => 'x', 'user_id' => $admin->id, 'status' => 'active', 'lead_time_days' => 30]);
        $att = app(AttachmentStorage::class)->store(
            UploadedFile::fake()->createWithContent('test.pdf', 'pdf-content')->mimeType('application/pdf'),
            $asset, null, $admin->id, 'Vertrag',
        );
        return [$admin, $att];
    }

    public function test_create_share_link_caps_to_admin_max(): void
    {
        [$admin, $att] = $this->setupAttachment();
        Settings::set('shares.max_expiry_days', 30);

        $this->actingAs($admin)->post(route('shares.store', $att), [
            'expires_in_days' => 365, // wird auf 30 gecapt
        ])->assertRedirect();

        $share = ShareLink::firstOrFail();
        $this->assertTrue($share->expires_at->diffInDays(now()) <= 30);
    }

    public function test_public_share_renders_with_inline_preview(): void
    {
        [$admin, $att] = $this->setupAttachment();
        $this->actingAs($admin)->post(route('shares.store', $att))->assertRedirect();
        $share = ShareLink::firstOrFail();

        $this->get(route('share.show', $share->token))
            ->assertOk()
            ->assertSee($att->original_name);

        $r = $this->get(route('share.preview', $share->token));
        $r->assertOk();
        $this->assertStringStartsWith('inline', strtolower($r->headers->get('content-disposition')));

        $this->assertDatabaseHas('share_link_accesses', ['share_link_id' => $share->id, 'action' => 'view']);
    }

    public function test_password_protected_share_requires_unlock(): void
    {
        [$admin, $att] = $this->setupAttachment();
        $this->actingAs($admin)->post(route('shares.store', $att), [
            'password' => 'geheim',
        ])->assertRedirect();
        $share = ShareLink::firstOrFail();

        // Public-User: erst Passwort-Seite
        $this->get(route('share.show', $share->token))
            ->assertOk()
            ->assertSee('Passwort');

        // Falsches Passwort -> Fehler
        $this->post(route('share.unlock', $share->token), ['password' => 'falsch'])
            ->assertRedirect()->assertSessionHasErrors('password');

        // Richtiges Passwort -> Session-Unlock
        $this->post(route('share.unlock', $share->token), ['password' => 'geheim'])
            ->assertRedirect(route('share.show', $share->token));
        $this->get(route('share.show', $share->token))->assertOk()->assertSee($att->original_name);
    }

    public function test_expired_or_revoked_link_returns_unavailable(): void
    {
        [$admin, $att] = $this->setupAttachment();
        $share = ShareLink::create([
            'attachment_id' => $att->id, 'created_by' => $admin->id,
            'expires_at' => now()->subDay(),
        ]);
        $this->get(route('share.show', $share->token))->assertOk()->assertSee('Freigabe nicht');

        $share2 = ShareLink::create(['attachment_id' => $att->id, 'created_by' => $admin->id, 'expires_at' => now()->addDay()]);
        $share2->revoke('manuell');
        $this->get(route('share.show', $share2->token))->assertOk()->assertSee('Freigabe nicht');
    }

    public function test_download_increments_counter_and_respects_limit(): void
    {
        [$admin, $att] = $this->setupAttachment();
        $share = ShareLink::create([
            'attachment_id' => $att->id, 'created_by' => $admin->id,
            'expires_at' => now()->addDay(), 'max_downloads' => 1,
        ]);
        $this->get(route('share.download', $share->token))->assertOk();
        $this->assertSame(1, $share->fresh()->download_count);
        // Zweiter Aufruf -> Limit erreicht
        $this->get(route('share.download', $share->token))->assertNotFound();
    }

    public function test_review_command_sends_mail_after_interval(): void
    {
        [$admin, $att] = $this->setupAttachment();
        Settings::set('shares.review_interval_days', 7);
        Settings::set('shares.review_grace_days', 3);
        Mail::fake();

        $share = ShareLink::create([
            'attachment_id' => $att->id, 'created_by' => $admin->id,
            'expires_at' => now()->addMonths(2),
        ]);
        // Erstellung 10 Tage in der Vergangenheit simulieren
        $share->forceFill(['created_at' => now()->subDays(10)])->save();

        $this->artisan('shares:review')->assertSuccessful();

        Mail::assertSent(\App\Mail\ShareReviewMail::class, fn ($m) => $m->hasTo($admin->email));
        $this->assertNotNull($share->fresh()->last_review_sent_at);
    }

    public function test_no_response_within_grace_period_auto_revokes(): void
    {
        [$admin, $att] = $this->setupAttachment();
        Settings::set('shares.review_interval_days', 7);
        Settings::set('shares.review_grace_days', 3);
        Mail::fake();

        $share = ShareLink::create([
            'attachment_id' => $att->id, 'created_by' => $admin->id,
            'expires_at' => now()->addMonths(2),
        ]);
        // Pruefung wurde vor 5 Tagen verschickt — Reaktionsfrist (3 Tage) ist abgelaufen
        $share->forceFill(['last_review_sent_at' => now()->subDays(5)])->save();

        $this->artisan('shares:review')->assertSuccessful();
        $share->refresh();
        $this->assertTrue($share->is_revoked);
        $this->assertNotNull($share->revoked_at);
        Mail::assertSent(\App\Mail\ShareAutoRevokedMail::class);
    }

    public function test_review_confirm_via_signed_url_marks_response_and_resets_clock(): void
    {
        [$admin, $att] = $this->setupAttachment();
        $share = ShareLink::create([
            'attachment_id' => $att->id, 'created_by' => $admin->id,
            'expires_at' => now()->addMonths(2),
        ]);
        $share->forceFill(['last_review_sent_at' => now()->subDay()])->save();

        $url = URL::temporarySignedRoute('shares.review.confirm', now()->addDays(3), ['share' => $share->id]);
        $this->get($url)->assertOk()->assertSee('Grund');

        $submitUrl = URL::temporarySignedRoute('shares.review.confirm.submit', now()->addDays(3), ['share' => $share->id]);
        $this->post($submitUrl, ['reason' => 'Vertrag mit Anwalt bis 30.06.'])
            ->assertOk()
            ->assertSee('Freigabe bleibt aktiv');

        $share->refresh();
        $this->assertSame('Vertrag mit Anwalt bis 30.06.', $share->review_response);
        $this->assertNotNull($share->last_review_response_at);
        $this->assertFalse($share->is_revoked);
    }

    public function test_review_revoke_via_signed_url(): void
    {
        [$admin, $att] = $this->setupAttachment();
        $share = ShareLink::create([
            'attachment_id' => $att->id, 'created_by' => $admin->id,
            'expires_at' => now()->addMonths(2),
        ]);
        $url = URL::temporarySignedRoute('shares.review.revoke', now()->addDays(3), ['share' => $share->id]);
        $this->get($url)->assertOk()->assertSee('widerrufen');
        $this->assertTrue($share->fresh()->is_revoked);
    }

    public function test_unsigned_review_link_returns_403(): void
    {
        [$admin, $att] = $this->setupAttachment();
        $share = ShareLink::create(['attachment_id' => $att->id, 'created_by' => $admin->id]);
        $this->get(route('shares.review.confirm', ['share' => $share->id]))->assertForbidden();
    }

    public function test_user_cannot_revoke_someone_elses_share(): void
    {
        [$admin, $att] = $this->setupAttachment();
        $other = User::factory()->create();
        $other->assignRole('employee');

        $share = ShareLink::create(['attachment_id' => $att->id, 'created_by' => $admin->id, 'expires_at' => now()->addDay()]);

        $this->actingAs($other)->post(route('shares.revoke', $share))->assertForbidden();
    }
}
