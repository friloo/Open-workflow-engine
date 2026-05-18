<?php

namespace App\Http\Controllers\Lists;

use App\Http\Controllers\Controller;
use App\Models\LookupList;
use App\Models\LookupListEntry;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LookupListEntryController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function store(Request $request, LookupList $list): RedirectResponse
    {
        $payload = $request->validate(['cells' => ['required', 'array']])['cells'];
        $keyColumn = $list->keyColumn();
        $keyValue = trim((string) ($payload[$keyColumn['key']] ?? ''));
        if ($keyValue === '') {
            return back()->withErrors(['key' => 'Schluesselwert darf nicht leer sein.']);
        }

        LookupListEntry::updateOrCreate(
            ['lookup_list_id' => $list->id, 'key_value' => $keyValue],
            ['data' => $payload],
        );

        return back()->with('status', "Eintrag {$keyValue} gespeichert.");
    }

    public function destroy(LookupList $list, LookupListEntry $entry): RedirectResponse
    {
        abort_unless($entry->lookup_list_id === $list->id, 404);
        $key = $entry->key_value;
        $entry->delete();
        $this->audit->log('list.entry.deleted', $list, ['key_value' => $key], null, "Eintrag {$key} aus Liste {$list->name} geloescht");
        return back()->with('status', "Eintrag {$key} geloescht.");
    }

    public function import(Request $request, LookupList $list): RedirectResponse
    {
        $request->validate([
            'csv' => ['required', 'file', 'mimes:csv,txt', 'max:5120'],
            'delimiter' => ['nullable', 'string', 'size:1'],
        ]);

        $delimiter = $request->input('delimiter', ';');
        $handle = fopen($request->file('csv')->getRealPath(), 'r');
        if (! $handle) {
            return back()->withErrors(['csv' => 'CSV konnte nicht gelesen werden.']);
        }

        $header = fgetcsv($handle, 0, $delimiter);
        if (! $header) {
            fclose($handle);
            return back()->withErrors(['csv' => 'CSV ist leer.']);
        }

        // Normalize header: lowercase + snake
        $header = array_map(fn ($h) => Str::of($h)->trim()->lower()->snake()->toString(), $header);
        $columnKeys = collect($list->columns)->pluck('key')->all();
        $mapping = [];
        foreach ($header as $i => $h) {
            if (in_array($h, $columnKeys, true)) {
                $mapping[$h] = $i;
            }
        }

        $keyColumn = $list->keyColumn();
        if (! isset($mapping[$keyColumn['key']])) {
            fclose($handle);
            return back()->withErrors(['csv' => "Pflichtspalte '{$keyColumn['key']}' fehlt in der CSV."]);
        }

        $imported = 0; $updated = 0; $failed = 0; $errors = [];
        $row = 1;
        while (($line = fgetcsv($handle, 0, $delimiter)) !== false) {
            $row++;
            if (count(array_filter($line, fn ($v) => $v !== '' && $v !== null)) === 0) continue;

            $data = [];
            foreach ($mapping as $col => $idx) {
                $v = trim((string) ($line[$idx] ?? ''));
                $data[$col] = $v === '' ? null : $v;
            }
            $keyValue = $data[$keyColumn['key']] ?? null;
            if ($keyValue === null || $keyValue === '') {
                $failed++; $errors[] = "Zeile {$row}: Schluesselwert fehlt.";
                continue;
            }

            $existing = LookupListEntry::where('lookup_list_id', $list->id)->where('key_value', $keyValue)->first();
            if ($existing) {
                $existing->update(['data' => $data]); $updated++;
            } else {
                LookupListEntry::create(['lookup_list_id' => $list->id, 'key_value' => $keyValue, 'data' => $data]);
                $imported++;
            }
        }
        fclose($handle);

        $this->audit->log('list.imported', $list, null, compact('imported', 'updated', 'failed'),
            "CSV-Import in Liste {$list->name}: {$imported} neu, {$updated} aktualisiert, {$failed} fehlerhaft");

        return back()->with('status',
            "Import abgeschlossen: {$imported} neu, {$updated} aktualisiert, {$failed} fehlerhaft.")
            ->with('importErrors', $errors);
    }

    public function export(LookupList $list): StreamedResponse
    {
        $cols = $list->columns;
        $filename = 'liste-'.$list->slug.'-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($list, $cols) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, array_map(fn ($c) => $c['key'], $cols), ';');
            $list->entries()->orderBy('key_value')->chunk(500, function ($chunk) use ($out, $cols) {
                foreach ($chunk as $e) {
                    $row = [];
                    foreach ($cols as $c) $row[] = $e->data[$c['key']] ?? '';
                    fputcsv($out, $row, ';');
                }
            });
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
