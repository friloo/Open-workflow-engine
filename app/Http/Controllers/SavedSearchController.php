<?php

namespace App\Http\Controllers;

use App\Models\SavedSearch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Speichert benutzerspezifische Filter-Kombinationen ('Saved Searches'),
 * aktuell nur fuer die Dokumenten-Liste (scope='documents'). Die Liste
 * der gespeicherten Suchen pro User taucht in der Doku-Liste als Chips
 * auf — Klick wendet die Filter wieder an.
 */
class SavedSearchController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:128'],
            'scope' => ['required', 'in:documents'],
            'params' => ['required', 'array'],
        ]);

        // Maximal 30 pro User + Scope — verhindert Datenmuell.
        $existing = SavedSearch::where('user_id', $request->user()->id)
            ->where('scope', $data['scope'])->count();
        if ($existing >= 30) {
            return back()->withErrors(['name' => 'Maximal 30 gespeicherte Suchen — bitte zuerst eine loeschen.']);
        }

        SavedSearch::create([
            'user_id' => $request->user()->id,
            'scope' => $data['scope'],
            'name' => $data['name'],
            'params' => $this->cleanParams($data['params']),
            'sort_order' => $existing,
        ]);

        return back()->with('status', 'Suche „'.$data['name'].'" gespeichert.');
    }

    public function destroy(Request $request, SavedSearch $savedSearch): RedirectResponse
    {
        // Nur eigene loeschbar
        if ($savedSearch->user_id !== $request->user()->id) abort(403);
        $savedSearch->delete();
        return back()->with('status', 'Suche geloescht.');
    }

    /**
     * Filtert alles weg, was nicht zum Standard-Filter-Set gehoert —
     * verhindert dass jemand zufaellig sensible Daten in params parkt.
     */
    private function cleanParams(array $params): array
    {
        $allowed = ['q', 'type', 'status', 'fields'];
        $out = array_intersect_key($params, array_flip($allowed));
        // 'fields' darf nur skalare oder from/to-Paare enthalten.
        if (isset($out['fields']) && is_array($out['fields'])) {
            $clean = [];
            foreach ($out['fields'] as $key => $val) {
                if (! preg_match('/^[a-z0-9_]+$/i', (string) $key)) continue;
                if (is_array($val)) {
                    $clean[$key] = array_intersect_key($val, ['from' => 1, 'to' => 1]);
                } else {
                    $clean[$key] = (string) $val;
                }
            }
            $out['fields'] = $clean;
        }
        return $out;
    }
}
