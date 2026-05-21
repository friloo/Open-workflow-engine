<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Secret;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SecretController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(): View
    {
        return view('admin.secrets.index', [
            'secrets' => Secret::with('creator')->orderBy('key')->paginate(50),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'key' => ['required', 'string', 'max:64', 'regex:/^[a-z][a-z0-9_]*$/', Rule::unique('secrets', 'key')],
            'value' => ['required', 'string', 'max:8192'],
            'description' => ['nullable', 'string', 'max:255'],
        ]);
        $s = Secret::create([...$data, 'created_by' => $request->user()->id]);
        $this->audit->log('secret.created', $s, null, ['key' => $s->key], "Secret {$s->key} angelegt");
        return back()->with('status', "Secret {$s->key} gespeichert.");
    }

    public function update(Request $request, Secret $secret): RedirectResponse
    {
        $data = $request->validate([
            'value' => ['nullable', 'string', 'max:8192'],
            'description' => ['nullable', 'string', 'max:255'],
        ]);
        if (! empty($data['value'])) {
            $secret->value = $data['value'];
        }
        if (array_key_exists('description', $data)) $secret->description = $data['description'];
        $secret->save();
        $this->audit->log('secret.updated', $secret, null, ['key' => $secret->key], "Secret {$secret->key} aktualisiert");
        return back()->with('status', 'Secret aktualisiert.');
    }

    public function destroy(Secret $secret): RedirectResponse
    {
        $key = $secret->key;
        $secret->delete();
        $this->audit->log('secret.deleted', null, ['key' => $key], null, "Secret {$key} gelöscht");
        return back()->with('status', "Secret {$key} gelöscht.");
    }
}
