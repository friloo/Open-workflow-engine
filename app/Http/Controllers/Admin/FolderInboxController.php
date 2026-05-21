<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FolderInbox;
use App\Models\Workflow;
use App\Services\AuditLogger;
use App\Services\FolderInboxScanner;
use App\Support\DocumentTypes;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FolderInboxController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(): View
    {
        return view('admin.folder-inboxes.index', [
            'inboxes' => FolderInbox::with('workflow')->orderBy('name')->paginate(25),
        ]);
    }

    public function create(): View
    {
        return view('admin.folder-inboxes.edit', [
            'inbox' => new FolderInbox([
                'is_active' => true,
                'after_import' => 'delete',
                'processed_subfolder' => 'verarbeitet',
                'use_storage_disk' => false,
            ]),
            'workflows' => Workflow::orderBy('name')->get(['id', 'name', 'status']),
            'documentTypes' => DocumentTypes::all(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateInbox($request);
        $inbox = new FolderInbox($data);
        $inbox->is_active = $request->boolean('is_active', true);
        $inbox->use_storage_disk = $request->boolean('use_storage_disk', false);
        $inbox->extensions = $this->parseExtensions($data['extensions_raw'] ?? null);
        $inbox->created_by = $request->user()->id;
        $inbox->save();

        $this->audit->log('folder_inbox.created', $inbox, null, [
            'name' => $inbox->name, 'path' => $inbox->path,
        ], "Folder-Inbox {$inbox->name} angelegt");

        return redirect()->route('admin.folder-inboxes.index')->with('status', 'Folder gespeichert.');
    }

    public function edit(FolderInbox $folderInbox): View
    {
        return view('admin.folder-inboxes.edit', [
            'inbox' => $folderInbox,
            'workflows' => Workflow::orderBy('name')->get(['id', 'name', 'status']),
            'documentTypes' => DocumentTypes::all(),
        ]);
    }

    public function update(Request $request, FolderInbox $folderInbox): RedirectResponse
    {
        $data = $this->validateInbox($request, $folderInbox);
        $folderInbox->fill($data);
        $folderInbox->is_active = $request->boolean('is_active', false);
        $folderInbox->use_storage_disk = $request->boolean('use_storage_disk', false);
        $folderInbox->extensions = $this->parseExtensions($data['extensions_raw'] ?? null);
        $folderInbox->save();

        $this->audit->log('folder_inbox.updated', $folderInbox, null, [
            'name' => $folderInbox->name,
        ], "Folder-Inbox {$folderInbox->name} aktualisiert");

        return back()->with('status', 'Folder gespeichert.');
    }

    public function destroy(FolderInbox $folderInbox): RedirectResponse
    {
        $name = $folderInbox->name;
        $folderInbox->delete();
        $this->audit->log('folder_inbox.deleted', null, ['name' => $name], null, "Folder-Inbox {$name} gelöscht");
        return redirect()->route('admin.folder-inboxes.index')->with('status', 'Folder gelöscht.');
    }

    public function scan(FolderInbox $folderInbox, FolderInboxScanner $scanner): RedirectResponse
    {
        try {
            $stats = $scanner->scan($folderInbox);
            return back()->with('status', sprintf(
                'gefunden: %d, importiert: %d, fehler: %d.',
                $stats['found'], $stats['imported'], $stats['failed']
            ));
        } catch (\Throwable $e) {
            return back()->withErrors(['inbox' => $e->getMessage()]);
        }
    }

    private function validateInbox(Request $request, ?FolderInbox $inbox = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'path' => ['required', 'string', 'max:1024'],
            'document_type' => ['nullable', 'string', 'max:64'],
            'workflow_id' => ['nullable', 'exists:workflows,id'],
            'after_import' => ['required', 'in:delete,move'],
            'processed_subfolder' => ['required_if:after_import,move', 'nullable', 'string', 'max:128'],
            'extensions_raw' => ['nullable', 'string', 'max:255'],
        ]);
    }

    private function parseExtensions(?string $raw): ?array
    {
        $raw = trim((string) $raw);
        if ($raw === '') return null;
        $parts = preg_split('/[\s,]+/', strtolower($raw)) ?: [];
        $parts = array_values(array_filter(array_map(fn ($x) => ltrim($x, '.'), $parts)));
        return $parts ?: null;
    }
}
