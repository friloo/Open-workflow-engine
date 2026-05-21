<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CsvImport;
use App\Models\Role;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UserImportController extends Controller
{
    private const COLUMNS = [
        'name', 'email', 'department', 'job_title', 'phone', 'employee_id',
        'supervisor_email', 'role_slugs', 'is_active', 'email_notifications_enabled',
    ];

    public function __construct(private readonly AuditLogger $audit) {}

    public function show(): View
    {
        return view('admin.users.import', [
            'columns' => self::COLUMNS,
            'recent' => CsvImport::where('target', 'users')->latest()->limit(10)->get(),
            'roles' => Role::pluck('slug')->all(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'csv' => ['required', 'file', 'mimes:csv,txt', 'max:5120'],
            'delimiter' => ['nullable', 'string', 'size:1'],
            'default_role' => ['nullable', 'string', Rule::exists('roles', 'slug')],
            'send_password_setup' => ['nullable', 'boolean'],
        ]);

        $delimiter = $data['delimiter'] ?? ';';
        $defaultRoleSlug = $data['default_role'] ?? 'employee';
        $defaultRole = Role::where('slug', $defaultRoleSlug)->first();

        $path = $request->file('csv')->getRealPath();
        $handle = fopen($path, 'r');
        if (! $handle) {
            return back()->withErrors(['csv' => 'CSV-Datei konnte nicht gelesen werden.']);
        }

        $header = fgetcsv($handle, 0, $delimiter);
        if (! $header) {
            fclose($handle);
            return back()->withErrors(['csv' => 'CSV scheint leer zu sein.']);
        }

        $header = array_map(fn ($h) => Str::of($h)->trim()->lower()->snake()->toString(), $header);
        $mapping = [];
        foreach ($header as $i => $col) {
            if (in_array($col, self::COLUMNS, true)) {
                $mapping[$col] = $i;
            }
        }

        if (! isset($mapping['email']) || ! isset($mapping['name'])) {
            fclose($handle);
            return back()->withErrors(['csv' => 'CSV muss mindestens Spalten "name" und "email" enthalten.']);
        }

        $imported = 0;
        $skipped = 0;
        $failed = 0;
        $total = 0;
        $errors = [];

        DB::beginTransaction();
        try {
            while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
                if (count(array_filter($row, fn ($v) => $v !== '' && $v !== null)) === 0) {
                    continue;
                }
                $total++;

                $email = trim((string) ($row[$mapping['email']] ?? ''));
                $name = trim((string) ($row[$mapping['name']] ?? ''));

                if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $failed++;
                    $errors[] = "Zeile {$total}: ungültige E-Mail";
                    continue;
                }
                if ($name === '') {
                    $failed++;
                    $errors[] = "Zeile {$total}: Name fehlt";
                    continue;
                }

                $existing = User::withTrashed()->where('email', $email)->first();

                $payload = [
                    'name' => $name,
                    'department' => $this->cell($row, $mapping, 'department'),
                    'job_title' => $this->cell($row, $mapping, 'job_title'),
                    'phone' => $this->cell($row, $mapping, 'phone'),
                    'employee_id' => $this->cell($row, $mapping, 'employee_id'),
                    'is_active' => $this->bool($this->cell($row, $mapping, 'is_active'), true),
                    'email_notifications_enabled' => $this->bool($this->cell($row, $mapping, 'email_notifications_enabled'), true),
                ];

                if ($existing) {
                    if ($existing->trashed()) {
                        $existing->restore();
                    }
                    $existing->update($payload);
                    $user = $existing;
                    $skipped++;
                } else {
                    $user = User::create([
                        ...$payload,
                        'email' => $email,
                        'created_by' => $request->user()->id,
                    ]);
                    $imported++;
                }

                if ($supervisorEmail = $this->cell($row, $mapping, 'supervisor_email')) {
                    $supervisor = User::where('email', $supervisorEmail)->first();
                    if ($supervisor) {
                        $user->update(['supervisor_id' => $supervisor->id]);
                    }
                }

                $roleSlugs = $this->cell($row, $mapping, 'role_slugs');
                $roleIds = [];
                if ($roleSlugs) {
                    $slugs = preg_split('/[,;|]/', $roleSlugs, -1, PREG_SPLIT_NO_EMPTY);
                    $slugs = array_map('trim', $slugs);
                    $roleIds = Role::whereIn('slug', $slugs)->pluck('id')->all();
                }
                if (! $roleIds && $defaultRole) {
                    $roleIds = [$defaultRole->id];
                }
                if ($roleIds) {
                    $user->syncRoles($roleIds, $request->user()->id);
                }
            }

            $import = CsvImport::create([
                'target' => 'users',
                'user_id' => $request->user()->id,
                'original_filename' => $request->file('csv')->getClientOriginalName(),
                'rows_total' => $total,
                'rows_imported' => $imported,
                'rows_skipped' => $skipped,
                'rows_failed' => $failed,
                'errors' => $errors ?: null,
                'mapping' => $mapping,
            ]);

            $this->audit->log('users.imported', $import, null, [
                'total' => $total,
                'imported' => $imported,
                'updated' => $skipped,
                'failed' => $failed,
                'filename' => $import->original_filename,
            ], "CSV-Import: {$imported} angelegt, {$skipped} aktualisiert, {$failed} fehlerhaft");

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            fclose($handle);
            throw $e;
        }

        fclose($handle);

        return redirect()->route('admin.users.import')->with('status',
            "Import abgeschlossen: {$imported} angelegt, {$skipped} aktualisiert, {$failed} fehlerhaft.");
    }

    private function cell(array $row, array $mapping, string $col): ?string
    {
        if (! isset($mapping[$col])) {
            return null;
        }
        $v = trim((string) ($row[$mapping[$col]] ?? ''));
        return $v === '' ? null : $v;
    }

    private function bool(?string $v, bool $default): bool
    {
        if ($v === null) {
            return $default;
        }
        return in_array(strtolower($v), ['1', 'true', 'ja', 'yes', 'y', 'x'], true);
    }
}
