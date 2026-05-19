<?php

namespace App\Http\Controllers;

use Illuminate\Support\Str;
use Illuminate\View\View;
use League\CommonMark\GithubFlavoredMarkdownConverter;

class HelpController extends Controller
{
    private const DOCS_PATH = 'resources/docs';

    /**
     * Themen-Inhaltsverzeichnis. Gruppiert fuer die Sidebar.
     * Reihenfolge der Sections + der Themen ist gleichzeitig die Navigation.
     */
    private array $sections = [
        'Einstieg' => [
            'index' => 'Uebersicht',
            'install' => 'Erstinstallation (Web-Installer)',
            'first-steps' => 'Erste Schritte als Admin',
            'dashboard' => 'Dashboard / Startseite',
        ],
        'Cookbook & Beispiele' => [
            'cookbook-rechnungseingang' => 'Cookbook: Rechnungseingang',
        ],
        'Workflows entwerfen' => [
            'workflows' => 'Workflows entwerfen',
            'templates' => 'Workflow-Vorlagen (Import/Export)',
            'http-node' => 'HTTP-Knoten',
            'pdf-node' => 'PDF-Knoten',
            'simulation' => 'Workflow-Simulation',
            'ai' => 'KI im Designer',
            'stats' => 'Workflow-Statistik',
            'delegation' => 'Vertretungsregelung',
            'placeholders' => 'Platzhalter-Referenz',
        ],
        'Daten & Formulare' => [
            'forms' => 'Formulare',
            'lists' => 'Listen (Kostenstellen etc.)',
            'assets' => 'Assets (Fuehrerschein etc.)',
        ],
        'Dokumente' => [
            'documents' => 'Dokumente (Versionen, OCR, Bulk)',
            'document-schemas' => 'Felder-Schemas pro Dokumenttyp',
            'zugferd' => 'ZUGFeRD / XRechnung',
            'inbox-routing' => 'Postkorb + Lookup-Routing',
            'sharing' => 'Sharing-Links',
            'retention' => 'Aufbewahrungsregeln',
        ],
        'Eingang & Integrationen' => [
            'mailbox' => 'E-Mail-Eingang (IMAP)',
            'folder-inbox' => 'Folder-Inboxen (lokaler Ordner)',
            'mail-approval' => 'Genehmigung per Mail',
            'webhooks' => 'Webhooks (outgoing)',
            'incoming-webhooks' => 'Eingehende Webhooks',
            'secrets' => 'Secrets-Vault',
            'm365' => 'Microsoft 365',
            'api-tokens' => 'API-Tokens',
        ],
        'Sicherheit & Betrieb' => [
            '2fa' => 'Zwei-Faktor-Anmeldung',
            'revisionssicher' => 'Revisionssicherheit',
            'admin' => 'Administration',
            'health' => 'System-Health',
            'update' => 'System-Update',
            'backup' => 'Backup & Restore',
        ],
    ];

    public function index(): View
    {
        return $this->show('index');
    }

    public function show(string $topic): View
    {
        $topic = preg_replace('/[^a-z0-9_-]/', '', $topic) ?: 'index';
        $allTopics = $this->flatToc();
        if (! isset($allTopics[$topic])) abort(404);

        $file = base_path(self::DOCS_PATH.'/'.$topic.'.md');
        $md = file_exists($file)
            ? file_get_contents($file)
            : "# {$allTopics[$topic]}\n\nNoch keine Inhalte.";

        $md = $this->preprocessMarkdown($md);

        $converter = new GithubFlavoredMarkdownConverter([
            'html_input' => 'escape',
            'allow_unsafe_links' => false,
        ]);

        $html = (string) $converter->convert($md);
        $html = $this->postprocessHtml($html);

        return view('help.show', [
            'topic' => $topic,
            'title' => $allTopics[$topic],
            'html' => $html,
            'sections' => $this->sections,
            'toc' => $this->extractToc($html),
        ]);
    }

    /**
     * Macht das Markdown lebendig bevor es CommonMark sieht:
     *
     * - Loest interne Links '[Text](app:route.name)' bzw.
     *   '[Text](app:route.name?param=42)' in echte URLs auf.
     * - Wandelt GitHub-Style-Callouts '> [!NOTE]', '> [!TIP]',
     *   '> [!WARNING]', '> [!DANGER]' in HTML-Bloecke um (CommonMark
     *   uebernimmt den Rest als Roh-HTML).
     */
    private function preprocessMarkdown(string $md): string
    {
        // app:route.name -> /actual/url
        $md = preg_replace_callback(
            '/\]\(app:([a-zA-Z0-9_\.\-]+)(\?[^)]*)?\)/',
            function ($m) {
                $name = $m[1];
                $query = isset($m[2]) ? $this->parseQuery(substr($m[2], 1)) : [];
                try {
                    return '](' . route($name, $query) . ')';
                } catch (\Throwable) {
                    return $m[0]; // unbekannte Route → roh lassen
                }
            },
            $md,
        );

        // > [!NOTE] / [!TIP] / [!WARNING] / [!DANGER] / [!IMPORTANT]
        $md = preg_replace_callback(
            '/^>\s*\[!(NOTE|TIP|WARNING|DANGER|IMPORTANT)\]\s*\n((?:>.*(?:\n|$))+)/m',
            function ($m) {
                $kind = strtolower($m[1]);
                $body = preg_replace('/^>\s?/m', '', $m[2]);
                $bodyHtml = (new GithubFlavoredMarkdownConverter())->convert($body)->getContent();
                return "<div class=\"callout callout-{$kind}\">"
                    . "<div class=\"callout-title\">".ucfirst($kind)."</div>"
                    . "<div class=\"callout-body\">{$bodyHtml}</div>"
                    . "</div>\n\n";
            },
            $md,
        );

        return $md;
    }

    /**
     * Nach dem CommonMark-Run noch ein paar Tailwind-Klassen
     * auf die Standard-Tags packen, damit Tabellen/Code/Quotes auch
     * ohne explizites Markup gut aussehen.
     */
    private function postprocessHtml(string $html): string
    {
        // Tabellen lesbar machen.
        $html = preg_replace('/<table>/', '<table class="owe-table">', $html);

        // h2/h3 bekommen anchor-IDs (slug aus dem Heading-Text). Damit
        // funktioniert das TOC und Deep-Links a la /help/install#voraussetzungen.
        $usedIds = [];
        $html = preg_replace_callback(
            '/<(h[23])>(.+?)<\/\1>/i',
            function ($m) use (&$usedIds) {
                $tag = $m[1];
                $text = $m[2];
                $base = Str::slug(strip_tags($text)) ?: 'abschnitt';
                $id = $base; $i = 2;
                while (isset($usedIds[$id])) { $id = $base.'-'.$i++; }
                $usedIds[$id] = true;
                return "<{$tag} id=\"{$id}\">{$text}</{$tag}>";
            },
            $html,
        );
        return $html;
    }

    /**
     * Extrahiert h2/h3 aus dem gerenderten HTML fuer das Seiten-TOC.
     *
     * @return array<int, array{level:int,title:string,id:string}>
     */
    private function extractToc(string $html): array
    {
        preg_match_all('/<h([23])[^>]*id="([^"]+)"[^>]*>(.+?)<\/h[23]>/i', $html, $matches, PREG_SET_ORDER);
        $items = [];
        foreach ($matches as $m) {
            $items[] = [
                'level' => (int) $m[1],
                'id' => $m[2],
                'title' => strip_tags($m[3]),
            ];
        }
        return $items;
    }

    private function flatToc(): array
    {
        return array_merge(...array_values($this->sections));
    }

    private function parseQuery(string $q): array
    {
        parse_str($q, $out);
        return $out;
    }
}
