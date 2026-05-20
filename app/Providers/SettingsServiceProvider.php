<?php

namespace App\Providers;

use App\Support\Settings;
use Illuminate\Support\ServiceProvider;

class SettingsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->applyMailConfig();
        $this->applyM365Config();
        $this->applyBrandingConfig();
        $this->applyInfrastructureConfig();
    }

    /**
     * Storage / Queue / Suche / Office-Vorschau aus der DB ueberschreiben.
     * Default kommt weiter aus .env — die DB-Werte sind nur Overrides,
     * sodass eine frische Installation ohne UI-Zugriff sofort funktioniert
     * und das Admin-UI nur die Override-Schicht ist.
     */
    private function applyInfrastructureConfig(): void
    {
        $infra = Settings::group('infrastructure');
        if ($infra === []) return;

        // Storage-Disk fuer Attachments
        if (! empty($infra['attachments_disk'])) {
            config(['filesystems.attachments_disk' => $infra['attachments_disk']]);
        }
        // S3 / MinIO-Credentials (wenn welche gesetzt sind, ueberschreiben)
        $s3 = config('filesystems.disks.s3', []);
        foreach ([
            's3_key' => 'key',
            's3_secret' => 'secret',
            's3_region' => 'region',
            's3_bucket' => 'bucket',
            's3_endpoint' => 'endpoint',
            's3_url' => 'url',
        ] as $key => $target) {
            if (array_key_exists($key, $infra) && $infra[$key] !== '' && $infra[$key] !== null) {
                $s3[$target] = $infra[$key];
            }
        }
        if (! empty($infra['s3_use_path_style'])) {
            $s3['use_path_style_endpoint'] = true;
        }
        config(['filesystems.disks.s3' => $s3]);

        // Queue
        if (! empty($infra['queue_connection'])) {
            config(['queue.default' => $infra['queue_connection']]);
        }
        if (array_key_exists('queue_ocr', $infra)) {
            config(['app.queue_ocr' => (bool) $infra['queue_ocr']]);
        }

        // Suche
        if (! empty($infra['search_driver'])) {
            config(['search.driver' => $infra['search_driver']]);
        }
        if (! empty($infra['meilisearch_host'])) {
            config(['search.meilisearch.host' => $infra['meilisearch_host']]);
        }
        if (array_key_exists('meilisearch_key', $infra) && $infra['meilisearch_key'] !== '') {
            config(['search.meilisearch.key' => $infra['meilisearch_key']]);
        }

        // Office-Vorschau
        if (array_key_exists('libreoffice_preview', $infra)) {
            config(['app.libreoffice_preview' => (bool) $infra['libreoffice_preview']]);
        }
        if (! empty($infra['libreoffice_bin'])) {
            config(['app.libreoffice_bin' => $infra['libreoffice_bin']]);
        }
    }

    private function applyBrandingConfig(): void
    {
        $b = Settings::group('branding');
        if ($b === []) return;
        config(['branding' => [
            'app_name' => $b['app_name'] ?? config('app.name'),
            'primary_color' => $b['primary_color'] ?? '#6366f1',
            'logo_text' => $b['logo_text'] ?? 'W',
        ]]);
        if (! empty($b['app_name'])) config(['app.name' => $b['app_name']]);
    }

    private function applyMailConfig(): void
    {
        $mail = Settings::group('mail');
        if ($mail === []) return;

        $smtp = config('mail.mailers.smtp', []);
        $smtp['transport'] = 'smtp';
        if (! empty($mail['host']))       $smtp['host'] = $mail['host'];
        if (isset($mail['port']))         $smtp['port'] = (int) $mail['port'];
        if (array_key_exists('encryption', $mail)) $smtp['encryption'] = $mail['encryption'] ?: null;
        if (array_key_exists('username', $mail))   $smtp['username'] = $mail['username'] ?: null;
        if (array_key_exists('password', $mail))   $smtp['password'] = $mail['password'] ?: null;
        if (isset($mail['timeout']))      $smtp['timeout'] = (int) $mail['timeout'];

        config([
            'mail.default' => $mail['transport'] ?? config('mail.default'),
            'mail.mailers.smtp' => $smtp,
        ]);

        if (! empty($mail['from_address'])) config(['mail.from.address' => $mail['from_address']]);
        if (! empty($mail['from_name']))    config(['mail.from.name' => $mail['from_name']]);
    }

    private function applyM365Config(): void
    {
        $m365 = Settings::group('auth.m365');
        if ($m365 === []) return;

        $current = config('services.microsoft-azure', []);
        if (! empty($m365['client_id']))     $current['client_id'] = $m365['client_id'];
        if (! empty($m365['client_secret'])) $current['client_secret'] = $m365['client_secret'];
        if (! empty($m365['tenant_id']))     $current['tenant'] = $m365['tenant_id'];
        if (! empty($m365['redirect_uri']))  $current['redirect'] = $m365['redirect_uri'];
        $current['enabled'] = (bool) ($m365['enabled'] ?? false);
        $current['auto_provision'] = (bool) ($m365['auto_provision'] ?? true);
        $current['default_role'] = $m365['default_role'] ?? 'employee';

        config(['services.microsoft-azure' => $current]);
    }
}
