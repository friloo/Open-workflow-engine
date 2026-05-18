<?php

namespace App\Services\Update;

use App\Support\Settings;

/**
 * Channel-Factory: liefert den richtigen UpdateChannel basierend auf
 * der Konfiguration. Schuetzt davor, dass eine production-Instanz aus
 * Versehen development pullt (oder umgekehrt).
 */
class UpdateChannelFactory
{
    /**
     * @return array<string, UpdateChannel>
     */
    public static function all(): array
    {
        return [
            'stable' => new UpdateChannel(
                'stable',
                'Stable (Produktion)',
                'https://update.loheide.eu/open-workflow-engine',
            ),
            'development' => new UpdateChannel(
                'development',
                'Development (Vorschau)',
                'https://update.loheide.eu/open-workflow-engine-development',
            ),
        ];
    }

    public static function current(): UpdateChannel
    {
        $slug = (string) Settings::get('update.channel', 'stable');
        $channels = self::all();
        return $channels[$slug] ?? $channels['stable'];
    }
}
