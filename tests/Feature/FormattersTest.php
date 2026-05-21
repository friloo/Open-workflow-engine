<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class FormattersTest extends TestCase
{
    public function test_fmt_money_formats_german(): void
    {
        $html = Blade::render('<x-fmt-money :value="1234.5" />');
        $this->assertStringContainsString('1.234,50', $html);
        $this->assertStringContainsString('€', $html);
    }

    public function test_fmt_money_parses_german_string(): void
    {
        $html = Blade::render('<x-fmt-money value="1.234,56" />');
        $this->assertStringContainsString('1.234,56', $html);
    }

    public function test_fmt_money_null_shows_fallback(): void
    {
        $html = Blade::render('<x-fmt-money :value="null" />');
        $this->assertStringContainsString('—', $html);
    }

    public function test_fmt_bytes_humanizes(): void
    {
        $this->assertStringContainsString('1,5 KB', Blade::render('<x-fmt-bytes :value="1536" />'));
        $this->assertStringContainsString('2,5 MB', Blade::render('<x-fmt-bytes :value="2621440" />'));
    }

    public function test_fmt_date_formats_default(): void
    {
        $html = Blade::render('<x-fmt-date value="2026-05-20 14:32:00" />');
        $this->assertStringContainsString('20.05.2026', $html);
    }

    public function test_fmt_date_relative(): void
    {
        $html = Blade::render('<x-fmt-date :value="now()->subDay()" format="relative" />');
        // Carbon liefert je nach Locale "vor 1 Tag" oder "1 day ago" — beides akzeptieren
        $low = strtolower($html);
        $this->assertTrue(str_contains($low, 'ago') || str_contains($low, 'vor'), 'Relative-Format zeigt weder ago noch vor: '.$html);
    }
}
