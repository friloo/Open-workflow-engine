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
     *
     * Jedes Thema kann eine Permission-Liste angeben (ANY-of). Wenn leer,
     * sehen alle eingeloggten User es. Permissions werden gegen den
     * aktuellen User abgeglichen — sieht ein User die Permission nicht,
     * verschwindet das Thema aus der Sidebar UND der direkte Aufruf
     * der URL gibt 403.
     *
     * Struktur:
     *   'topic-slug' => ['label' => '…', 'any' => ['perm.a', 'perm.b']]
     *   'topic-slug' => 'Label nur'  (= jeder darf sehen)
     */
    private array $sections = [
        'Einstieg' => [
            'index' => 'Uebersicht',
            'install' => ['label' => 'Erstinstallation (Web-Installer)', 'any' => ['system.settings']],
            'first-steps' => ['label' => 'Erste Schritte als Admin', 'any' => ['system.settings']],
            'dashboard' => 'Dashboard / Startseite',
        ],
        'Cookbook & Beispiele' => [
            'cookbook-rechnungseingang' => ['label' => 'Cookbook: Rechnungseingang', 'any' => ['workflows.design', 'workflows.run']],
        ],
        'Workflows entwerfen' => [
            'workflows' => ['label' => 'Workflows entwerfen', 'any' => ['workflows.design', 'workflows.run', 'workflows.view']],
            'process-doc' => ['label' => 'Prozessbeschreibung als PDF', 'any' => ['workflows.view', 'workflows.design']],
            'templates' => ['label' => 'Workflow-Vorlagen (Import/Export)', 'any' => ['workflows.design']],
            'http-node' => ['label' => 'HTTP-Knoten', 'any' => ['workflows.design']],
            'pdf-node' => ['label' => 'PDF-Knoten', 'any' => ['workflows.design']],
            'simulation' => ['label' => 'Workflow-Simulation', 'any' => ['workflows.design']],
            'sub-workflows' => ['label' => 'Sub-Workflows & Loops', 'any' => ['workflows.design']],
            'ai' => ['label' => 'KI im Designer', 'any' => ['workflows.design']],
            'stats' => ['label' => 'Workflow-Statistik', 'any' => ['workflows.design', 'workflows.view']],
            'reports' => ['label' => 'Reports & KPI-Dashboard', 'any' => ['reports.view']],
            'delegation' => 'Vertretungsregelung',
            'notifications' => 'Benachrichtigungen anpassen',
            'placeholders' => ['label' => 'Platzhalter-Referenz', 'any' => ['workflows.design']],
        ],
        'Daten & Formulare' => [
            'forms' => ['label' => 'Formulare', 'any' => ['forms.view', 'forms.manage']],
            'lists' => ['label' => 'Listen (Kostenstellen etc.)', 'any' => ['lists.view', 'lists.manage']],
            'assets' => ['label' => 'Assets (Fuehrerschein etc.)', 'any' => ['assets.view', 'assets.manage']],
        ],
        'Dokumente' => [
            'documents' => ['label' => 'Dokumente (Versionen, OCR, Bulk)', 'any' => ['documents.search']],
            'office-preview' => ['label' => 'Office-Vorschau (LibreOffice)', 'any' => ['system.settings']],
            'annotations' => ['label' => 'Notizen & Stempel', 'any' => ['documents.search']],
            'search-meilisearch' => ['label' => 'Volltext-Suche skalieren (MeiliSearch)', 'any' => ['system.settings']],
            'document-schemas' => ['label' => 'Felder-Schemas pro Dokumenttyp', 'any' => ['system.settings']],
            'zugferd' => ['label' => 'ZUGFeRD / XRechnung', 'any' => ['documents.search']],
            'datev' => ['label' => 'DATEV-Export', 'any' => ['system.settings']],
            'inbox-routing' => ['label' => 'Postkorb + Lookup-Routing', 'any' => ['documents.search']],
            'sharing' => ['label' => 'Sharing-Links', 'any' => ['documents.search']],
            'retention' => ['label' => 'Aufbewahrungsregeln', 'any' => ['system.settings']],
        ],
        'Eingang & Integrationen' => [
            'mailbox' => ['label' => 'E-Mail-Eingang (IMAP)', 'any' => ['mailboxes.manage']],
            'folder-inbox' => ['label' => 'Folder-Inboxen (lokaler Ordner)', 'any' => ['folder_inboxes.manage']],
            'mail-approval' => 'Genehmigung per Mail',
            'webhooks' => ['label' => 'Webhooks (outgoing)', 'any' => ['webhooks.manage']],
            'incoming-webhooks' => ['label' => 'Eingehende Webhooks', 'any' => ['incoming_webhooks.manage']],
            'secrets' => ['label' => 'Secrets-Vault', 'any' => ['secrets.manage']],
            'sso' => ['label' => 'Anmeldung & SSO (M365, OIDC, Google, SAML, LDAP)', 'any' => ['system.settings']],
            'teams' => ['label' => 'Microsoft Teams (Notifications)', 'any' => ['system.settings']],
            'api-tokens' => 'API-Tokens',
        ],
        'Sicherheit & Betrieb' => [
            '2fa' => 'Zwei-Faktor-Anmeldung',
            'revisionssicher' => 'Revisionssicherheit',
            'gobd' => 'GoBD-Konformitaet (DE)',
            'dsgvo' => ['label' => 'DSGVO-Anfragen', 'any' => ['system.settings']],
            'admin' => ['label' => 'Administration', 'any' => ['system.settings']],
            'object-storage' => ['label' => 'Object-Storage (S3/MinIO)', 'any' => ['system.settings']],
            'queue-worker' => ['label' => 'Queue-Worker / Background-Jobs', 'any' => ['system.health']],
            'health' => ['label' => 'System-Health', 'any' => ['system.health']],
            'update' => ['label' => 'System-Update', 'any' => ['system.update']],
            'backup' => ['label' => 'Backup & Restore', 'any' => ['system.backup']],
        ],
    ];

    public function index(\Illuminate\Http\Request $request): View
    {
        return $this->show('index', $request);
    }

    public function show(string $topic, \Illuminate\Http\Request $request): View
    {
        $topic = preg_replace('/[^a-z0-9_-]/', '', $topic) ?: 'index';
        $allTopics = $this->flatToc();
        if (! isset($allTopics[$topic])) abort(404);

        $user = $request->user();
        if (! $this->userCanSeeTopic($user, $topic)) abort(403);

        $entry = $allTopics[$topic];
        $title = is_array($entry) ? $entry['label'] : $entry;

        $file = base_path(self::DOCS_PATH.'/'.$topic.'.md');
        $md = file_exists($file)
            ? file_get_contents($file)
            : "# {$title}\n\nNoch keine Inhalte.";

        $md = $this->preprocessMarkdown($md);

        $converter = new GithubFlavoredMarkdownConverter([
            'html_input' => 'escape',
            'allow_unsafe_links' => false,
        ]);

        $html = (string) $converter->convert($md);
        $html = $this->postprocessHtml($html);

        return view('help.show', [
            'topic' => $topic,
            'title' => $title,
            'html' => $html,
            'sections' => $this->visibleSections($user),
            'toc' => $this->extractToc($html),
        ]);
    }

    /**
     * Filtert das Section-Mapping auf das, was der User sehen darf.
     * Sections, deren saemtliche Themen rausgefiltert wurden, werden
     * komplett weggelassen — keine leeren Header.
     */
    private function visibleSections(?\App\Models\User $user): array
    {
        $out = [];
        foreach ($this->sections as $section => $topics) {
            $visible = [];
            foreach ($topics as $slug => $entry) {
                if ($this->userCanSeeTopic($user, $slug)) {
                    $visible[$slug] = is_array($entry) ? $entry['label'] : $entry;
                }
            }
            if (! empty($visible)) {
                $out[$section] = $visible;
            }
        }
        return $out;
    }

    private function userCanSeeTopic(?\App\Models\User $user, string $topic): bool
    {
        $entry = $this->flatToc()[$topic] ?? null;
        if (! $entry) return false;
        // Ohne Permission-Anforderung darf jeder Eingeloggte rein.
        if (! is_array($entry) || empty($entry['any'])) return true;
        if (! $user) return false;
        return $user->hasAnyPermission((array) $entry['any']);
    }

    /**
     * Macht das Markdown lebendig bevor es CommonMark sieht:
     *
     * - Loest interne Links '[Text](app:route.name)' bzw.
     *   '[Text](app:route.name?param=42)' in echte URLs auf.
     *
     * Callouts ('> [!NOTE]' etc.) werden NICHT hier verarbeitet,
     * sondern erst im postprocessHtml() — weil 'html_input: escape'
     * sonst rohes HTML zu sichtbarem Text macht.
     */
    private function preprocessMarkdown(string $md): string
    {
        return preg_replace_callback(
            '/\]\(app:([a-zA-Z0-9_\.\-]+)(\?[^)]*)?\)/',
            function ($m) {
                $name = $m[1];
                $query = isset($m[2]) ? $this->parseQuery(substr($m[2], 1)) : [];
                try {
                    return '](' . route($name, $query) . ')';
                } catch (\Throwable) {
                    return $m[0];
                }
            },
            $md,
        );
    }

    /**
     * Nach dem CommonMark-Run:
     *
     * - GitHub-Style-Callouts: CommonMark macht aus '> [!TIP]\n> body'
     *   ein '<blockquote><p>[!TIP]\nbody</p></blockquote>'. Den fangen
     *   wir hier ab und ersetzen ihn durch einen .callout-Block.
     * - Tabellen kriegen die owe-table-Klasse.
     * - h2/h3 bekommen slug-IDs fuer Deep-Links und das TOC.
     */
    private function postprocessHtml(string $html): string
    {
        // Callouts
        $html = preg_replace_callback(
            '/<blockquote>\s*<p>\s*\[!(NOTE|TIP|WARNING|DANGER|IMPORTANT)\]\s*(?:<br\s*\/?>|\n)?\s*(.*?)<\/p>\s*<\/blockquote>/is',
            function ($m) {
                $kind = strtolower($m[1]);
                $body = trim($m[2]);
                $titles = [
                    'note' => 'Hinweis',
                    'tip' => 'Tipp',
                    'warning' => 'Achtung',
                    'danger' => 'Wichtig',
                    'important' => 'Bitte beachten',
                ];
                $title = $titles[$kind] ?? ucfirst($kind);
                return "<div class=\"callout callout-{$kind}\">"
                    . "<div class=\"callout-title\">{$title}</div>"
                    . "<div class=\"callout-body\"><p>{$body}</p></div>"
                    . "</div>";
            },
            $html,
        );

        // Tabellen-Klasse
        $html = preg_replace('/<table>/', '<table class="owe-table">', $html);

        // h2/h3 Anchor-IDs
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

    /**
     * Flat list of all topics: slug => label-or-entry-array.
     */
    private function flatToc(): array
    {
        $out = [];
        foreach ($this->sections as $items) {
            foreach ($items as $slug => $entry) {
                $out[$slug] = $entry;
            }
        }
        return $out;
    }

    private function parseQuery(string $q): array
    {
        parse_str($q, $out);
        return $out;
    }
}
