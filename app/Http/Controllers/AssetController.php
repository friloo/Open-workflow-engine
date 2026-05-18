<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\User;
use App\Models\Workflow;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AssetController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(Request $request): View
    {
        $query = Asset::with('user', 'workflow')
            ->when($request->get('q'), fn ($q, $term) => $q->where('name', 'like', "%{$term}%")->orWhere('type', 'like', "%{$term}%"))
            ->when($request->get('status'), fn ($q, $s) => $q->where('status', $s))
            ->when($request->get('type'), fn ($q, $t) => $q->where('type', $t))
            ->orderBy('valid_until');

        return view('assets.index', [
            'assets' => $query->paginate(25)->withQueryString(),
            'types' => Asset::query()->distinct()->pluck('type')->filter()->values(),
            'search' => $request->get('q', ''),
            'status' => $request->get('status'),
            'type' => $request->get('type'),
        ]);
    }

    public function create(): View
    {
        return view('assets.edit', [
            'asset' => new Asset(['status' => 'active', 'lead_time_days' => 30]),
            'users' => User::where('is_active', true)->orderBy('name')->get(['id', 'name', 'email']),
            'workflows' => Workflow::active()->where('trigger_type', 'recurring')->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateAsset($request);
        $asset = Asset::create([...$data, 'created_by' => $request->user()->id]);
        $this->audit->log('asset.created', $asset, null, $asset->only(['id', 'name', 'type']),
            "Asset {$asset->name} angelegt");
        return redirect()->route('assets.index')->with('status', 'Asset angelegt.');
    }

    public function edit(Asset $asset): View
    {
        return view('assets.edit', [
            'asset' => $asset,
            'users' => User::where('is_active', true)->orderBy('name')->get(['id', 'name', 'email']),
            'workflows' => Workflow::active()->where('trigger_type', 'recurring')->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function update(Request $request, Asset $asset): RedirectResponse
    {
        $data = $this->validateAsset($request);
        $original = $asset->only(array_keys($data));
        $asset->update($data);
        $this->audit->log('asset.updated', $asset, $original, $asset->only(array_keys($data)),
            "Asset {$asset->name} aktualisiert");
        return back()->with('status', 'Asset gespeichert.');
    }

    public function destroy(Asset $asset): RedirectResponse
    {
        $snapshot = $asset->only(['id', 'name', 'type']);
        $asset->delete();
        $this->audit->log('asset.deleted', null, $snapshot, null, "Asset {$snapshot['name']} geloescht");
        return redirect()->route('assets.index')->with('status', 'Asset geloescht.');
    }

    public function import(Request $request): RedirectResponse
    {
        $request->validate([
            'csv' => ['required', 'file', 'mimes:csv,txt', 'max:5120'],
            'delimiter' => ['nullable', 'string', 'size:1'],
            'default_type' => ['nullable', 'string', 'max:128'],
        ]);

        $delimiter = $request->input('delimiter', ';');
        $defaultType = $request->input('default_type', 'asset');
        $handle = fopen($request->file('csv')->getRealPath(), 'r');
        if (! $handle) return back()->withErrors(['csv' => 'CSV konnte nicht gelesen werden.']);

        $header = fgetcsv($handle, 0, $delimiter);
        if (! $header) { fclose($handle); return back()->withErrors(['csv' => 'CSV ist leer.']); }
        $header = array_map(fn ($h) => Str::of($h)->trim()->lower()->snake()->toString(), $header);
        $expected = ['user_email', 'name', 'type', 'valid_until', 'lead_time_days', 'notes'];
        $mapping = [];
        foreach ($header as $i => $h) {
            if (in_array($h, $expected, true)) $mapping[$h] = $i;
        }
        if (! isset($mapping['user_email']) || ! isset($mapping['name'])) {
            fclose($handle);
            return back()->withErrors(['csv' => 'Spalten user_email und name sind Pflicht.']);
        }

        $imported = 0; $skipped = 0; $errors = [];
        $rowNum = 1;
        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
            $rowNum++;
            $email = strtolower(trim((string) ($row[$mapping['user_email']] ?? '')));
            $name = trim((string) ($row[$mapping['name']] ?? ''));
            if ($email === '' || $name === '') { $errors[] = "Zeile {$rowNum}: E-Mail oder Name fehlt."; continue; }
            $user = User::where('email', $email)->first();
            if (! $user) { $errors[] = "Zeile {$rowNum}: Benutzer {$email} unbekannt."; $skipped++; continue; }

            Asset::create([
                'user_id' => $user->id,
                'name' => $name,
                'type' => isset($mapping['type']) ? (trim((string)($row[$mapping['type']] ?? '')) ?: $defaultType) : $defaultType,
                'valid_until' => isset($mapping['valid_until']) ? (trim((string)($row[$mapping['valid_until']] ?? '')) ?: null) : null,
                'lead_time_days' => isset($mapping['lead_time_days']) ? (int) ($row[$mapping['lead_time_days']] ?? 30) : 30,
                'notes' => isset($mapping['notes']) ? (trim((string)($row[$mapping['notes']] ?? '')) ?: null) : null,
                'status' => 'active',
                'created_by' => $request->user()->id,
            ]);
            $imported++;
        }
        fclose($handle);

        $this->audit->log('asset.imported', null, null, compact('imported', 'skipped'),
            "Asset-CSV-Import: {$imported} angelegt, {$skipped} uebersprungen, ".count($errors)." Fehler",
            $request->user()->id);

        return back()->with('status', "Import abgeschlossen: {$imported} angelegt, {$skipped} uebersprungen, ".count($errors)." Fehler.")
            ->with('importErrors', $errors);
    }

    private function validateAsset(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', 'max:128'],
            'user_id' => ['required', 'integer', Rule::exists('users', 'id')->whereNull('deleted_at')],
            'valid_until' => ['nullable', 'date'],
            'workflow_id' => ['nullable', 'integer', Rule::exists('workflows', 'id')->whereNull('deleted_at')],
            'lead_time_days' => ['required', 'integer', 'min:0', 'max:365'],
            'status' => ['required', 'in:active,expired,archived'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
    }
}
