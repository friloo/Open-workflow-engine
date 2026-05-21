<?php

namespace App\Services\Update;

/**
 * Wert-Objekt für den Update-Channel. Pro Channel ein anderes Repo
 * im Proxy. Defaults siehe UpdateChannelFactory.
 */
final class UpdateChannel
{
    public function __construct(
        public readonly string $slug,         // 'stable' | 'development'
        public readonly string $label,
        public readonly string $baseUrl,      // https://update.loheide.eu/open-workflow-engine
    ) {}
}
