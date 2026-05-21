<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\User;
use App\Services\BackupService;
use App\Services\Installer\EnvWriter;
use App\Services\Installer\InstallChecker;
use App\Support\Installer;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Multi-Step-Installer:
 *
 *  1. /install              -> Welcome + System-Check
 *  2. /install/database     -> SQLite/MySQL wählen + speichern + Migrate
 *  3. /install/admin        -> Admin-User anlegen
 *  4. /install/finish       -> Marker setzen + ggf. Branding/Mail-Hinweis
 *
 * Wenn .installed bereits existiert, blockt RedirectIfNotInstalled
 * jeden Aufruf hierhin durch einen Hard-Redirect zur Startseite — der
 * Installer kann also nicht zweimal laufen.
 */
class InstallerController extends Controller
{
    public function __construct(private readonly InstallChecker $checker) {}

    public function welcome(): RedirectResponse|View
    {
        if ($r = $this->blockIfInstalled()) return $r;
        return view('install.welcome', [
            'checks' => $this->checker->checks(),
            'canProceed' => $this->checker->canProceed(),
        ]);
    }

    public function databaseShow(): RedirectResponse|View
    {
        if ($r = $this->blockIfInstalled()) return $r;
        return view('install.database', [
            'defaults' => [
                'driver' => 'sqlite',
                'host' => '127.0.0.1',
                'port' => '3306',
                'database' => '',
                'username' => '',
            ],
            'error' => null,
        ]);
    }

    public function databaseSave(Request $request): RedirectResponse|View
    {
        if ($r = $this->blockIfInstalled()) return $r;

        $data = $request->validate([
            'driver' => ['required', Rule::in(['sqlite', 'mysql'])],
            'host' => ['required_if:driver,mysql', 'nullable', 'string', 'max:255'],
            'port' => ['required_if:driver,mysql', 'nullable', 'integer', 'between:1,65535'],
            'database' => ['required_if:driver,mysql', 'nullable', 'string', 'max:255'],
            'username' => ['required_if:driver,mysql', 'nullable', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'max:255'],
            'app_name' => ['required', 'string', 'max:128'],
            'app_url' => ['required', 'url', 'max:255'],
        ]);

        $env = EnvWriter::default();
        $env->ensureFile();

        // APP_KEY generieren wenn leer
        if (empty(env('APP_KEY'))) {
            $env->set(['APP_KEY' => $env->generateAppKey()]);
        }

        $env->set([
            'APP_NAME' => $data['app_name'],
            'APP_URL' => rtrim($data['app_url'], '/'),
            'APP_ENV' => 'production',
            'APP_DEBUG' => 'false',
        ]);

        if ($data['driver'] === 'sqlite') {
            $sqlitePath = database_path('database.sqlite');
            if (! is_file($sqlitePath)) {
                @touch($sqlitePath);
            }
            if (! is_writable($sqlitePath)) {
                return view('install.database', [
                    'defaults' => $data,
                    'error' => "SQLite-Datei nicht schreibbar: {$sqlitePath}",
                ]);
            }
            $env->set([
                'DB_CONNECTION' => 'sqlite',
                'DB_DATABASE' => $sqlitePath,
                'DB_HOST' => null, 'DB_PORT' => null, 'DB_USERNAME' => null, 'DB_PASSWORD' => null,
            ]);
            Config::set('database.default', 'sqlite');
            Config::set('database.connections.sqlite.database', $sqlitePath);
        } else {
            $cfg = [
                'driver' => 'mysql',
                'host' => $data['host'],
                'port' => (int) $data['port'],
                'database' => $data['database'],
                'username' => $data['username'],
                'password' => (string) ($data['password'] ?? ''),
                'charset' => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
                'prefix' => '',
                'strict' => true,
            ];
            Config::set('database.default', 'mysql');
            Config::set('database.connections.mysql', $cfg);
            DB::purge('mysql');
            try {
                DB::connection('mysql')->getPdo();
            } catch (\Throwable $e) {
                return view('install.database', [
                    'defaults' => $data,
                    'error' => 'Verbindung fehlgeschlagen: '.$e->getMessage(),
                ]);
            }
            $env->set([
                'DB_CONNECTION' => 'mysql',
                'DB_HOST' => (string) $data['host'],
                'DB_PORT' => (string) $data['port'],
                'DB_DATABASE' => (string) $data['database'],
                'DB_USERNAME' => (string) $data['username'],
                'DB_PASSWORD' => (string) ($data['password'] ?? ''),
            ]);
        }

        try {
            DB::purge();
            DB::reconnect();
            Artisan::call('migrate', ['--force' => true]);
            (new RolesAndPermissionsSeeder())->run();
        } catch (\Throwable $e) {
            return view('install.database', [
                'defaults' => $data,
                'error' => 'Migrationen/Seeder fehlgeschlagen: '.$e->getMessage(),
            ]);
        }

        return redirect()->route('install.admin');
    }

    public function adminShow(): RedirectResponse|View
    {
        if ($r = $this->blockIfInstalled()) return $r;
        if (! $this->dbReady()) return redirect()->route('install.database');
        return view('install.admin');
    }

    public function adminSave(Request $request): RedirectResponse|View
    {
        if ($r = $this->blockIfInstalled()) return $r;
        if (! $this->dbReady()) return redirect()->route('install.database');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $role = Role::where('slug', 'admin')->first();
        if (! $role) {
            return view('install.admin')->withErrors([
                'name' => 'Admin-Rolle nicht gefunden. Wurde der Seeder im DB-Schritt ausgeführt?',
            ]);
        }

        $user = User::updateOrCreate(
            ['email' => $data['email']],
            ['name' => $data['name'], 'password' => Hash::make($data['password']), 'is_active' => true],
        );
        $user->roles()->syncWithoutDetaching([$role->id]);

        return redirect()->route('install.finish');
    }

    public function finishShow(): RedirectResponse|View
    {
        if ($r = $this->blockIfInstalled()) return $r;
        if (! $this->dbReady() || ! User::query()->exists()) {
            return redirect()->route('install.database');
        }

        Installer::markInstalled();

        return view('install.finish');
    }

    // ─── Restore-Pfad ──────────────────────────────────────────────────

    public function restoreShow(): RedirectResponse|View
    {
        if ($r = $this->blockIfInstalled()) return $r;
        return view('install.restore', [
            'defaults' => [
                'driver' => 'sqlite', 'host' => '127.0.0.1', 'port' => '3306',
                'database' => '', 'username' => '',
                'app_name' => 'OWE', 'app_url' => url('/'),
            ],
            'error' => null,
            'uploadMaxMb' => $this->uploadLimitMb(),
        ]);
    }

    public function restoreSave(Request $request, BackupService $backup): RedirectResponse|View
    {
        if ($r = $this->blockIfInstalled()) return $r;

        $data = $request->validate([
            'driver' => ['required', Rule::in(['sqlite', 'mysql'])],
            'host' => ['required_if:driver,mysql', 'nullable', 'string', 'max:255'],
            'port' => ['required_if:driver,mysql', 'nullable', 'integer', 'between:1,65535'],
            'database' => ['required_if:driver,mysql', 'nullable', 'string', 'max:255'],
            'username' => ['required_if:driver,mysql', 'nullable', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'max:255'],
            'app_name' => ['required', 'string', 'max:128'],
            'app_url' => ['required', 'url', 'max:255'],
            'backup_file' => ['required', 'file'],
            'confirm' => ['accepted'],
        ], [
            'confirm.accepted' => 'Bitte bestätige, dass bestehende Daten überschrieben werden dürfen.',
        ]);

        $upload = $request->file('backup_file');
        if ($upload->getClientOriginalExtension() !== 'zip') {
            return $this->restoreError($data, 'Bitte eine .zip-Datei hochladen.');
        }

        // 1. ZIP in storage/app/backups/ verschieben, damit BackupService den Pfad kennt.
        @mkdir($backup->backupDir(), 0775, true);
        $safeName = 'owe-'.now()->format('Y-m-d_His').'.zip';
        if (! $upload->move($backup->backupDir(), $safeName)) {
            return $this->restoreError($data, 'Upload konnte nicht gespeichert werden.');
        }
        $zipPath = $backup->backupDir().'/'.$safeName;

        // 2. Manifest prüfen
        $zip = new \ZipArchive();
        if ($zip->open($zipPath) !== true) {
            @unlink($zipPath);
            return $this->restoreError($data, 'ZIP konnte nicht geöffnet werden.');
        }
        $manifest = json_decode((string) $zip->getFromName('manifest.json'), true);
        $zip->close();
        if (! is_array($manifest) || ($manifest['owe_backup'] ?? 0) !== 1) {
            @unlink($zipPath);
            return $this->restoreError($data, 'Kein OWE-Backup (manifest.json fehlt).');
        }

        // 3. Driver des Backups muss zum gewählten Driver passen
        $manifestDriver = (string) ($manifest['driver'] ?? 'sqlite');
        if (! in_array($manifestDriver, ['sqlite', 'mysql', 'mariadb'], true)) {
            @unlink($zipPath);
            return $this->restoreError($data, "Driver im Backup ({$manifestDriver}) nicht unterstützt.");
        }
        $expectedDriver = $manifestDriver === 'mariadb' ? 'mysql' : $manifestDriver;
        if ($data['driver'] !== $expectedDriver) {
            @unlink($zipPath);
            return $this->restoreError($data,
                "Backup wurde mit {$manifestDriver} erstellt, du hast {$data['driver']} gewählt. ".
                'Bitte den passenden Treiber wählen.');
        }

        // 4. .env schreiben (inkl. APP_KEY)
        $env = EnvWriter::default();
        $env->ensureFile();
        if (empty(env('APP_KEY'))) {
            $env->set(['APP_KEY' => $env->generateAppKey()]);
        }
        $env->set([
            'APP_NAME' => $data['app_name'],
            'APP_URL' => rtrim($data['app_url'], '/'),
            'APP_ENV' => 'production',
            'APP_DEBUG' => 'false',
        ]);

        if ($data['driver'] === 'sqlite') {
            $sqlitePath = database_path('database.sqlite');
            if (! is_file($sqlitePath)) @touch($sqlitePath);
            $env->set([
                'DB_CONNECTION' => 'sqlite',
                'DB_DATABASE' => $sqlitePath,
                'DB_HOST' => null, 'DB_PORT' => null, 'DB_USERNAME' => null, 'DB_PASSWORD' => null,
            ]);
            Config::set('database.default', 'sqlite');
            Config::set('database.connections.sqlite.database', $sqlitePath);
        } else {
            $cfg = [
                'driver' => 'mysql', 'host' => $data['host'], 'port' => (int) $data['port'],
                'database' => $data['database'], 'username' => $data['username'],
                'password' => (string) ($data['password'] ?? ''),
                'charset' => 'utf8mb4', 'collation' => 'utf8mb4_unicode_ci', 'prefix' => '', 'strict' => true,
            ];
            Config::set('database.default', 'mysql');
            Config::set('database.connections.mysql', $cfg);
            DB::purge('mysql');
            try {
                DB::connection('mysql')->getPdo();
            } catch (\Throwable $e) {
                @unlink($zipPath);
                return $this->restoreError($data, 'MySQL-Verbindung fehlgeschlagen: '.$e->getMessage());
            }
            $env->set([
                'DB_CONNECTION' => 'mysql',
                'DB_HOST' => (string) $data['host'],
                'DB_PORT' => (string) $data['port'],
                'DB_DATABASE' => (string) $data['database'],
                'DB_USERNAME' => (string) $data['username'],
                'DB_PASSWORD' => (string) ($data['password'] ?? ''),
            ]);
        }

        DB::purge();
        DB::reconnect();

        // 5. Restore
        try {
            $backup->restore($safeName);
        } catch (\Throwable $e) {
            return $this->restoreError($data, 'Wiederherstellung fehlgeschlagen: '.$e->getMessage());
        }

        // 6. Schema migrieren (falls Backup aelter ist als aktueller Code-Stand)
        try {
            DB::purge();
            DB::reconnect();
            Artisan::call('migrate', ['--force' => true]);
        } catch (\Throwable $e) {
            return $this->restoreError($data, 'Migrationen nach Restore fehlgeschlagen: '.$e->getMessage());
        }

        Installer::markInstalled();

        return redirect()->route('install.restoreDone');
    }

    public function restoreDone(): RedirectResponse|View
    {
        // Nicht von blockIfInstalled blockieren, weil wir gerade installiert HABEN.
        return view('install.restore-done');
    }

    private function restoreError(array $data, string $error): View
    {
        return view('install.restore', [
            'defaults' => $data + ['driver' => 'sqlite'],
            'error' => $error,
            'uploadMaxMb' => $this->uploadLimitMb(),
        ]);
    }

    private function uploadLimitMb(): int
    {
        $parse = function (string $v): int {
            $v = trim($v);
            if ($v === '') return 0;
            $last = strtolower(substr($v, -1));
            $n = (int) $v;
            return match ($last) {
                'g' => $n * 1024,
                'm' => $n,
                'k' => max(1, (int) ($n / 1024)),
                default => max(1, (int) ($n / 1024 / 1024)),
            };
        };
        $upload = $parse((string) ini_get('upload_max_filesize'));
        $post = $parse((string) ini_get('post_max_size'));
        return max(1, min($upload, $post));
    }

    private function blockIfInstalled(): ?RedirectResponse
    {
        return Installer::isInstalled() ? redirect('/') : null;
    }

    private function dbReady(): bool
    {
        try {
            DB::connection()->getPdo();
            return Schema::hasTable('users') && Schema::hasTable('roles');
        } catch (\Throwable) {
            return false;
        }
    }
}
