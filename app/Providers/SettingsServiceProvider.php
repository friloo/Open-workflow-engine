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
