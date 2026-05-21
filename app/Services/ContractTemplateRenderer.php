<?php

namespace App\Services;

use App\Models\Contract;
use App\Models\ContractTemplate;

/**
 * Rendert Vertrags-Vorlagen mit Mustache-Platzhaltern zu HTML.
 * Unterstuetzte Platzhalter:
 *   {{ name }}, {{ party }}, {{ start_date }}, {{ end_date }},
 *   {{ notice_period_days }}, {{ owner.name }}, {{ owner.email }},
 *   {{ type.name }}, {{ today }}, plus benutzerdefinierte {{ data.X }}
 *   wenn beim Render-Aufruf $extra mitgegeben wird.
 */
class ContractTemplateRenderer
{
    public function render(ContractTemplate $template, Contract $contract, array $extra = []): string
    {
        $vars = $this->variablesFor($contract, $extra);
        return $this->replacePlaceholders($template->body_html, $vars);
    }

    public function variablesFor(Contract $contract, array $extra = []): array
    {
        return [
            'name' => $contract->name,
            'party' => $contract->party ?? '',
            'description' => $contract->description ?? '',
            'start_date' => $contract->start_date?->format('d.m.Y') ?? '',
            'end_date' => $contract->end_date?->format('d.m.Y') ?? '',
            'notice_period_days' => (string) $contract->notice_period_days,
            'notice_deadline' => $contract->noticeDeadline()?->format('d.m.Y') ?? '',
            'auto_renew' => $contract->auto_renew ? 'ja' : 'nein',
            'auto_renew_months' => (string) ($contract->auto_renew_months ?? ''),
            'owner.name' => $contract->owner?->name ?? '',
            'owner.email' => $contract->owner?->email ?? '',
            'type.name' => $contract->type?->name ?? '',
            'today' => now()->format('d.m.Y'),
            'app.name' => (string) config('app.name'),
        ] + collect($extra)->mapWithKeys(fn ($v, $k) => ["data.{$k}" => is_scalar($v) ? (string) $v : json_encode($v)])->all();
    }

    private function replacePlaceholders(string $template, array $vars): string
    {
        return preg_replace_callback('/\{\{\s*([a-zA-Z0-9_.]+)\s*\}\}/', function ($m) use ($vars) {
            return array_key_exists($m[1], $vars) ? htmlspecialchars($vars[$m[1]], ENT_QUOTES, 'UTF-8') : '';
        }, $template);
    }
}
