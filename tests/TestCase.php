<?php

namespace Tests;

use App\Support\Settings;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Settings::flush();
        // Marker fuer "installed", damit RedirectIfNotInstalled-Middleware
        // Tests nicht zum Installer redirected. Installer-Tests muessen den
        // Marker selbst entfernen.
        @file_put_contents(storage_path('app/.installed'), '{"installed_at":"test"}');
    }
}
