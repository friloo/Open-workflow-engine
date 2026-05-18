<?php

namespace App\Http\Controllers;

use Illuminate\Support\Str;
use Illuminate\View\View;
use League\CommonMark\GithubFlavoredMarkdownConverter;

class HelpController extends Controller
{
    private const DOCS_PATH = 'resources/docs';

    private array $toc = [
        'index' => 'Uebersicht',
        'first-steps' => 'Erste Schritte',
        'workflows' => 'Workflows entwerfen',
        'ai' => 'KI im Designer',
        'forms' => 'Formulare',
        'lists' => 'Listen (Kostenstellen etc.)',
        'assets' => 'Assets (Fuehrerschein etc.)',
        'documents' => 'Dokumente (Versionen, OCR, Bulk)',
        'http-node' => 'HTTP-Knoten',
        'secrets' => 'Secrets-Vault',
        'webhooks' => 'Webhooks',
        'm365' => 'Microsoft 365',
        'admin' => 'Administration',
        'revisionssicher' => 'Revisionssicherheit',
        'placeholders' => 'Platzhalter-Referenz',
    ];

    public function index(): View
    {
        return $this->show('index');
    }

    public function show(string $topic): View
    {
        $topic = preg_replace('/[^a-z0-9_-]/', '', $topic) ?: 'index';
        if (! isset($this->toc[$topic])) abort(404);

        $file = base_path(self::DOCS_PATH.'/'.$topic.'.md');
        $md = file_exists($file) ? file_get_contents($file) : "# {$this->toc[$topic]}\n\nNoch keine Inhalte.";

        $converter = new GithubFlavoredMarkdownConverter([
            'html_input' => 'escape',
            'allow_unsafe_links' => false,
        ]);

        return view('help.show', [
            'topic' => $topic,
            'title' => $this->toc[$topic],
            'html' => (string) $converter->convert($md),
            'toc' => $this->toc,
        ]);
    }
}
