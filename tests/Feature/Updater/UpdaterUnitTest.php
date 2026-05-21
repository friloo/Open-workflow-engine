<?php

namespace Tests\Feature\Updater;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Updater\MigrationsRunner;
use Updater\SqlSplitter;
use Updater\StagingApplier;
use Updater\UpdaterFactory;
use Updater\UpdateManager;

class UpdaterUnitTest extends TestCase
{
    use RefreshDatabase;

    public function test_sql_splitter_respects_strings_and_comments(): void
    {
        $sql = "CREATE TABLE x (a TEXT); -- a comment with ;\nINSERT INTO x VALUES ('hello; world');\n/* block ; ignored */ UPDATE x SET a='b';";
        $stmts = SqlSplitter::split($sql);
        $this->assertCount(3, $stmts);
        $this->assertStringContainsString('hello; world', $stmts[1]);
    }

    public function test_sql_splitter_ignorable_errors_per_driver(): void
    {
        $this->assertTrue(SqlSplitter::isIgnorableSqlError('Table users already exists', 'mysql'));
        $this->assertTrue(SqlSplitter::isIgnorableSqlError('Duplicate column name "x"', 'mariadb'));
        $this->assertTrue(SqlSplitter::isIgnorableSqlError('table foo already exists', 'sqlite'));
        $this->assertFalse(SqlSplitter::isIgnorableSqlError('foreign key violation', 'mysql'));
    }

    public function test_staging_applier_protects_critical_paths(): void
    {
        $a = new StagingApplier(sys_get_temp_dir());
        $this->assertTrue($a->isProtected('config/app.php'));
        $this->assertTrue($a->isProtected('storage/app/test.txt'));
        $this->assertTrue($a->isProtected('.env'));
        $this->assertTrue($a->isProtected('vendor/composer/something.php'));
        $this->assertTrue($a->isProtected('database/database.sqlite'));
        $this->assertTrue($a->isProtected('my.sqlite'));
        $this->assertTrue($a->isProtected('storage.db'));
        // Updater selbst NICHT geschützt
        $this->assertFalse($a->isProtected('updater/src/UpdateManager.php'));
        // App-Code NICHT geschützt
        $this->assertFalse($a->isProtected('app/Services/X.php'));
    }

    public function test_migrations_runner_creates_tracking_table_and_runs_sql(): void
    {
        $dir = sys_get_temp_dir().'/owe-updater-test-'.uniqid();
        mkdir($dir);
        file_put_contents($dir.'/001_make_dummy.sql',
            'CREATE TABLE IF NOT EXISTS _updater_dummy (id INTEGER PRIMARY KEY, name TEXT);');

        $runner = new MigrationsRunner(DB::connection(), $dir);
        $applied = $runner->migrate();
        $this->assertSame(1, $applied);

        $status = $runner->status();
        $this->assertContains('001_make_dummy.sql', $status['applied']);
        $this->assertEmpty($status['pending']);

        // Idempotent — zweimal laufen lassen ist 0 neue
        $this->assertSame(0, $runner->migrate());

        DB::statement('DROP TABLE IF EXISTS _updater_dummy');
        DB::statement('DROP TABLE IF EXISTS _updater_migrations');
        unlink($dir.'/001_make_dummy.sql');
        rmdir($dir);
    }

    public function test_update_manager_version_io(): void
    {
        $root = sys_get_temp_dir().'/owe-updater-mgr-'.uniqid();
        mkdir($root);
        $m = new UpdateManager(DB::connection(), null, 'stable', projectRoot: $root);

        $this->assertNull($m->getCurrentVersion());

        $sha = str_repeat('a', 40);
        $m->saveCurrentVersion($sha);
        $this->assertSame($sha, $m->getCurrentVersion());

        $this->expectException(\InvalidArgumentException::class);
        $m->saveCurrentVersion('not-a-sha');
    }

    public function test_update_manager_maintenance_toggle(): void
    {
        $root = sys_get_temp_dir().'/owe-updater-maint-'.uniqid();
        mkdir($root);
        $m = new UpdateManager(DB::connection(), null, 'stable', projectRoot: $root);

        $this->assertFalse($m->isInMaintenance());
        $m->maintenanceOn();
        $this->assertTrue($m->isInMaintenance());
        $m->maintenanceOff();
        $this->assertFalse($m->isInMaintenance());
    }

    public function test_updater_factory_loads_channel_from_settings_file(): void
    {
        $root = sys_get_temp_dir().'/owe-updater-fact-'.uniqid();
        mkdir($root);
        UpdaterFactory::saveSettings($root, ['channel' => 'development']);

        $m = UpdaterFactory::create(DB::connection(), null, $root);
        $this->assertSame('development', $m->channel());
    }

    public function test_updater_factory_falls_back_to_stable_on_unknown_channel(): void
    {
        $root = sys_get_temp_dir().'/owe-updater-fb-'.uniqid();
        mkdir($root);
        UpdaterFactory::saveSettings($root, ['channel' => 'bogus']);

        $m = UpdaterFactory::create(DB::connection(), null, $root);
        $this->assertSame('stable', $m->channel());
    }
}
