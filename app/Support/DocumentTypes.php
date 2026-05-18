<?php

namespace App\Support;

use App\Models\User;

class DocumentTypes
{
    /**
     * Definierte Dokumenttyp-Namen (flache Liste, ohne Berechtigungen).
     *
     * @return array<int, string>
     */
    public static function all(): array
    {
        $raw = (array) Settings::get('attachments.document_types', []);
        $out = [];
        foreach ($raw as $t) {
            if (is_string($t)) {
                $out[] = $t;
            } elseif (is_array($t) && ! empty($t['name'])) {
                $out[] = (string) $t['name']; // Backward-Compat
            }
        }
        return array_values(array_unique(array_filter(array_map('trim', $out))));
    }

    /**
     * Mapping Role-Slug -> [Type-Name]. Verwaltet via Systemeinstellungen.
     *
     * @return array<string, array<int,string>>
     */
    public static function roleMapping(): array
    {
        $raw = (array) Settings::get('attachments.role_document_types', []);
        $out = [];
        foreach ($raw as $roleSlug => $types) {
            if (! is_string($roleSlug)) continue;
            $out[$roleSlug] = array_values(array_filter(array_map(fn ($t) => is_string($t) ? trim($t) : null, (array) $types)));
        }
        return $out;
    }

    /**
     * Liste der Typ-Namen, die dieser User sehen darf. Admin sieht alles.
     * Nicht klassifizierte Dokumente (NULL document_type) sind fuer jeden
     * Suchberechtigten sichtbar.
     */
    public static function visibleForUser(User $user): array
    {
        if ($user->hasRole('admin')) return self::all();
        $mapping = self::roleMapping();
        $userRoles = $user->roles->pluck('slug')->all();
        $allowed = [];
        foreach ($userRoles as $slug) {
            foreach (($mapping[$slug] ?? []) as $type) {
                $allowed[] = $type;
            }
        }
        return array_values(array_unique($allowed));
    }

    public static function canViewType(User $user, ?string $type): bool
    {
        if ($type === null || $type === '') return true;
        if ($user->hasRole('admin')) return true;
        return in_array($type, self::visibleForUser($user), true);
    }
}
