<?php

namespace App\Http\Controllers;

use App\Models\Tag;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TagController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(): View
    {
        return view('tags.index', [
            'tags' => Tag::withCount('attachments')->orderBy('name')->paginate(50),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:64'],
            'color' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ]);
        $tag = Tag::create([
            'name' => $data['name'],
            'color' => $data['color'] ?? '#64748b',
            'created_by' => $request->user()->id,
        ]);
        $this->audit->log('tag.created', $tag, null, $tag->only(['id', 'name', 'slug']),
            "Tag {$tag->name} angelegt");
        return back()->with('status', 'Tag angelegt.');
    }

    public function update(Request $request, Tag $tag): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:64'],
            'color' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ]);
        $tag->update([
            'name' => $data['name'],
            'color' => $data['color'] ?? $tag->color,
        ]);
        return back()->with('status', 'Tag gespeichert.');
    }

    public function destroy(Tag $tag): RedirectResponse
    {
        $name = $tag->name;
        $tag->delete();
        $this->audit->log('tag.deleted', null, ['name' => $name], null, "Tag {$name} gelöscht");
        return back()->with('status', "Tag {$name} gelöscht.");
    }
}
