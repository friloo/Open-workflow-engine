<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Mailbox;
use App\Models\Workflow;
use App\Services\AuditLogger;
use App\Services\MailboxFetcher;
use App\Support\DocumentTypes;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class MailboxController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(): View
    {
        return view('admin.mailboxes.index', [
            'mailboxes' => Mailbox::with('workflow')->orderBy('name')->paginate(25),
        ]);
    }

    public function create(): View
    {
        return view('admin.mailboxes.edit', [
            'mailbox' => new Mailbox([
                'port' => 993,
                'encryption' => 'ssl',
                'validate_cert' => true,
                'folder' => 'INBOX',
                'processed_folder' => 'Verarbeitet',
                'move_processed' => true,
                'is_active' => true,
            ]),
            'workflows' => Workflow::orderBy('name')->get(['id', 'name']),
            'documentTypes' => DocumentTypes::all(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateMailbox($request);
        $password = $data['password'] ?? null;
        unset($data['password']);

        $mailbox = new Mailbox($data);
        $mailbox->created_by = $request->user()->id;
        $mailbox->is_active = $request->boolean('is_active', true);
        $mailbox->validate_cert = $request->boolean('validate_cert', true);
        $mailbox->ai_classify = $request->boolean('ai_classify', false);
        $mailbox->move_processed = $request->boolean('move_processed', true);
        if ($password) $mailbox->password = $password;
        $mailbox->save();

        $this->audit->log('mailbox.created', $mailbox, null, [
            'name' => $mailbox->name, 'host' => $mailbox->host, 'username' => $mailbox->username,
        ], "Postfach {$mailbox->name} angelegt");

        return redirect()->route('admin.mailboxes.index')->with('status', 'Postfach gespeichert.');
    }

    public function edit(Mailbox $mailbox): View
    {
        return view('admin.mailboxes.edit', [
            'mailbox' => $mailbox,
            'workflows' => Workflow::orderBy('name')->get(['id', 'name']),
            'documentTypes' => DocumentTypes::all(),
        ]);
    }

    public function update(Request $request, Mailbox $mailbox): RedirectResponse
    {
        $data = $this->validateMailbox($request, $mailbox);
        $password = $data['password'] ?? null;
        unset($data['password']);

        $original = $mailbox->only(['name', 'host', 'username', 'document_type', 'workflow_id']);
        $mailbox->fill($data);
        $mailbox->is_active = $request->boolean('is_active', false);
        $mailbox->validate_cert = $request->boolean('validate_cert', false);
        $mailbox->ai_classify = $request->boolean('ai_classify', false);
        $mailbox->move_processed = $request->boolean('move_processed', false);
        if ($password) $mailbox->password = $password;
        $mailbox->save();

        $this->audit->log('mailbox.updated', $mailbox, $original, $mailbox->only(array_keys($original)),
            "Postfach {$mailbox->name} aktualisiert");

        return back()->with('status', 'Postfach gespeichert.');
    }

    public function destroy(Mailbox $mailbox): RedirectResponse
    {
        $snapshot = $mailbox->only(['id', 'name', 'host']);
        $mailbox->delete();
        $this->audit->log('mailbox.deleted', null, $snapshot, null, "Postfach {$snapshot['name']} gelöscht");
        return redirect()->route('admin.mailboxes.index')->with('status', 'Postfach gelöscht.');
    }

    public function test(Mailbox $mailbox, MailboxFetcher $fetcher): RedirectResponse
    {
        try {
            $count = $fetcher->testConnection($mailbox);
            return back()->with('status', "Verbindung OK. Ungelesene Nachrichten: {$count}.");
        } catch (\Throwable $e) {
            return back()->withErrors(['mailbox' => 'Verbindung fehlgeschlagen: '.$e->getMessage()]);
        }
    }

    public function fetch(Mailbox $mailbox, MailboxFetcher $fetcher): RedirectResponse
    {
        try {
            $stats = $fetcher->fetch($mailbox);
            return back()->with('status', sprintf(
                'Abgerufen: %d, verarbeitet: %d, übersprungen: %d, Fehler: %d.',
                $stats['fetched'], $stats['processed'], $stats['skipped'], $stats['failed']
            ));
        } catch (\Throwable $e) {
            return back()->withErrors(['mailbox' => 'Abruf fehlgeschlagen: '.$e->getMessage()]);
        }
    }

    private function validateMailbox(Request $request, ?Mailbox $mailbox = null): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'host' => ['required', 'string', 'max:255'],
            'port' => ['required', 'integer', 'between:1,65535'],
            'encryption' => ['required', Rule::in(['ssl', 'tls', 'none'])],
            'username' => ['required', 'string', 'max:255'],
            'password' => [$mailbox ? 'nullable' : 'required', 'string', 'max:255'],
            'folder' => ['required', 'string', 'max:255'],
            'document_type' => ['nullable', 'string', 'max:64'],
            'workflow_id' => ['nullable', 'exists:workflows,id'],
            'subject_field' => ['nullable', 'string', 'max:64'],
            'from_field' => ['nullable', 'string', 'max:64'],
            'body_field' => ['nullable', 'string', 'max:64'],
            'processed_folder' => ['required_with:move_processed', 'nullable', 'string', 'max:255'],
        ];
        return $request->validate($rules);
    }
}
