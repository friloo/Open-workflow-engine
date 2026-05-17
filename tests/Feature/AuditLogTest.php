<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_chain_is_intact_after_multiple_writes(): void
    {
        $logger = app(AuditLogger::class);
        $user = User::factory()->create();

        $logger->log('test.a', $user, null, ['v' => 1]);
        $logger->log('test.b', $user, ['v' => 1], ['v' => 2]);
        $logger->log('test.c', $user, ['v' => 2], ['v' => 3]);

        $this->assertSame(3, AuditLog::count());
        $this->assertNull($logger->verifyChain());
    }

    public function test_tampering_breaks_the_chain(): void
    {
        $logger = app(AuditLogger::class);
        $user = User::factory()->create();

        $logger->log('test.a', $user, null, ['v' => 1]);
        $logger->log('test.b', $user, ['v' => 1], ['v' => 2]);

        // Bypass model guards to simulate a database-level manipulation
        AuditLog::query()->whereKey(1)->getQuery()->update(['description' => 'manipulated']);

        $result = $logger->verifyChain();
        $this->assertNotNull($result);
        $this->assertSame(1, $result['broken_at_id']);
    }

    public function test_eloquent_update_is_blocked(): void
    {
        $logger = app(AuditLogger::class);
        $entry = $logger->log('test.x');

        $this->expectException(\RuntimeException::class);
        $entry->update(['description' => 'nope']);
    }

    public function test_eloquent_delete_is_blocked(): void
    {
        $logger = app(AuditLogger::class);
        $entry = $logger->log('test.x');

        $this->expectException(\RuntimeException::class);
        $entry->delete();
    }
}
