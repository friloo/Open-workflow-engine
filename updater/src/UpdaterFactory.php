<?php

namespace Updater;

use Illuminate\Database\ConnectionInterface;

/**
 * Erzeugt einen UpdateManager und holt sich den Channel aus einer
 * isolierten Settings-Datei (updater-settings.json im projectRoot).
 *
 * Bewusst NICHT in Laravels Config/Settings-System: der Updater bleibt
 * komplett aushebelbar. Beim Rueckbau einfach Datei loeschen.
 */
final class UpdaterFactory
{
    public static function create(ConnectionInterface $db, mixed $audit = null, ?string $projectRoot = null): UpdateManager
    {
        $root = $projectRoot ?? dirname(__DIR__, 2);
        $settings = self::loadSettings($root);
        $channel = $settings['channel'] ?? 'stable';
        if (! isset(UpdateManager::CHANNELS[$channel])) {
            $channel = 'stable';
        }
        return new UpdateManager($db, $audit, $channel, projectRoot: $root);
    }

    /** @return array<string, mixed> */
    public static function loadSettings(string $projectRoot): array
    {
        $f = $projectRoot.'/updater-settings.json';
        if (! is_file($f)) return [];
        $j = json_decode((string) file_get_contents($f), true);
        return is_array($j) ? $j : [];
    }

    public static function saveSettings(string $projectRoot, array $settings): void
    {
        file_put_contents(
            $projectRoot.'/updater-settings.json',
            json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE).PHP_EOL,
        );
    }
}
