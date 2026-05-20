<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Verwaltet die User-Praeferenz Matrix Event x Channel. Default-Verhalten
 * fuer noch nicht ueberschriebene Kombinationen: an. So funktionieren
 * frische Installationen / neue User wie bisher.
 *
 * Plus ein Lookup-Cache pro Request, damit die n*m-Pruefung beim
 * Massenversand (Reminder-Cron, Eskalation an Rolle) nicht n*m Queries
 * macht.
 */
class NotificationPreferences
{
    /**
     * Liste aller in der UI editierbaren Event-Keys mit Label + Beschreibung.
     * Andere Event-Keys koennen vorkommen — sie greifen dann auf Default.
     *
     * @return array<string, array{label:string, description:string}>
     */
    public static function catalog(): array
    {
        return [
            'task.assigned' => [
                'label' => 'Aufgabe zugewiesen',
                'description' => 'Wenn dir oder einer deiner Rollen eine neue Aufgabe zugewiesen wird.',
            ],
            'task.reminder' => [
                'label' => 'Erinnerung an offene Aufgabe',
                'description' => 'Wenn eine Aufgabe ueberfaellig ist oder bald die Frist erreicht.',
            ],
            'task.completed' => [
                'label' => 'Eigene Aufgabe abgeschlossen',
                'description' => 'Bestaetigung dass deine Entscheidung gespeichert ist.',
            ],
            'task.escalated' => [
                'label' => 'Aufgabe eskaliert',
                'description' => 'Wenn die Karenzzeit abgelaufen ist und die Aufgabe an einen anderen Empfaenger geht.',
            ],
            'workflow.completed' => [
                'label' => 'Vorgang abgeschlossen',
                'description' => 'Wenn ein von dir gestarteter Workflow durchgelaufen ist.',
            ],
            'workflow.failed' => [
                'label' => 'Vorgang fehlgeschlagen',
                'description' => 'Wenn ein von dir gestarteter Workflow mit Fehler stehen bleibt.',
            ],
            'document.shared' => [
                'label' => 'Sharing-Link erstellt',
                'description' => 'Wenn jemand einen oeffentlichen Freigabe-Link zu einem Dokument erstellt.',
            ],
            'mention' => [
                'label' => 'Erwaehnung in Kommentar',
                'description' => 'Wenn du in einem Vorgangs-Kommentar mit @ erwaehnt wirst.',
            ],
        ];
    }

    public static function channels(): array
    {
        return [
            'in_app' => 'In-App (Glocken-Icon)',
            'mail' => 'E-Mail',
        ];
    }

    /**
     * Liefert true wenn der User diese Event-Channel-Kombination erhalten will.
     * Default fuer fehlende Eintraege: true (= Opt-out-Modell).
     *
     * Spezialfall Mail: zusaetzlich muss die globale Flag
     * users.email_notifications_enabled = true sein (das ist die schon
     * vorhandene Master-Abschaltung pro User).
     */
    public static function wants(User $user, string $eventKey, string $channel): bool
    {
        // Master-Switch fuer Mails ueberschreibt alles
        if ($channel === 'mail' && ! $user->email_notifications_enabled) return false;

        $matrix = self::matrixFor($user);
        $key = $eventKey.':'.$channel;
        if (! array_key_exists($key, $matrix)) return true; // Default
        return (bool) $matrix[$key];
    }

    /**
     * Komplette Matrix fuer einen User als hash 'event:channel' => bool.
     * Per-Request gecached um wiederholte Queries zu vermeiden.
     *
     * @return array<string, bool>
     */
    public static function matrixFor(User $user): array
    {
        static $cache = [];
        if (isset($cache[$user->id])) return $cache[$user->id];

        // Defensive: ohne Migration einfach Defaults verwenden
        if (! Schema::hasTable('user_notification_preferences')) {
            return $cache[$user->id] = [];
        }

        $rows = DB::table('user_notification_preferences')
            ->where('user_id', $user->id)
            ->get(['event_key', 'channel', 'enabled']);

        $matrix = [];
        foreach ($rows as $r) {
            $matrix[$r->event_key.':'.$r->channel] = (bool) $r->enabled;
        }
        return $cache[$user->id] = $matrix;
    }

    /**
     * Schreibt die User-Praeferenz. Wird vom Profile-Form gerufen.
     * Werte ausserhalb der Catalog/Channel-Listen werden ignoriert.
     */
    public static function set(User $user, string $eventKey, string $channel, bool $enabled): void
    {
        if (! array_key_exists($eventKey, self::catalog())) return;
        if (! array_key_exists($channel, self::channels())) return;
        if (! Schema::hasTable('user_notification_preferences')) return;

        DB::table('user_notification_preferences')
            ->updateOrInsert(
                ['user_id' => $user->id, 'event_key' => $eventKey, 'channel' => $channel],
                ['enabled' => $enabled, 'updated_at' => now(), 'created_at' => now()],
            );
    }
}
