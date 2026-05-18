<?php

namespace App\Providers;

use App\Support\Settings;
use Illuminate\Support\ServiceProvider;

class SettingsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $mail = Settings::group('mail');
        if ($mail === []) {
            return;
        }

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

        if (! empty($mail['from_address'])) {
            config(['mail.from.address' => $mail['from_address']]);
        }
        if (! empty($mail['from_name'])) {
            config(['mail.from.name' => $mail['from_name']]);
        }
    }
}
