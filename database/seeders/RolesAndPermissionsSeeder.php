<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            // User-Verwaltung
            ['slug' => 'users.view', 'name' => 'Benutzer ansehen', 'group' => 'Benutzer'],
            ['slug' => 'users.create', 'name' => 'Benutzer anlegen', 'group' => 'Benutzer'],
            ['slug' => 'users.update', 'name' => 'Benutzer bearbeiten', 'group' => 'Benutzer'],
            ['slug' => 'users.delete', 'name' => 'Benutzer loeschen', 'group' => 'Benutzer'],
            ['slug' => 'users.import', 'name' => 'Benutzer importieren (CSV/M365)', 'group' => 'Benutzer'],

            // Rollen
            ['slug' => 'roles.view', 'name' => 'Rollen ansehen', 'group' => 'Rollen'],
            ['slug' => 'roles.manage', 'name' => 'Rollen und Rechte verwalten', 'group' => 'Rollen'],

            // Workflows
            ['slug' => 'workflows.view', 'name' => 'Workflows ansehen', 'group' => 'Workflows'],
            ['slug' => 'workflows.design', 'name' => 'Workflows entwerfen & aktivieren', 'group' => 'Workflows'],
            ['slug' => 'workflows.run', 'name' => 'Workflows starten', 'group' => 'Workflows'],

            // Formulare
            ['slug' => 'forms.view', 'name' => 'Formulare ansehen', 'group' => 'Formulare'],
            ['slug' => 'forms.manage', 'name' => 'Formulare verwalten', 'group' => 'Formulare'],

            // Audit
            ['slug' => 'audit.view', 'name' => 'Audit-Log ansehen', 'group' => 'Audit'],
            ['slug' => 'audit.verify', 'name' => 'Audit-Kette pruefen', 'group' => 'Audit'],

            // Lookup-Listen (Kostenstellen, Abteilungen, ...)
            ['slug' => 'lists.view', 'name' => 'Listen ansehen', 'group' => 'Listen'],
            ['slug' => 'lists.manage', 'name' => 'Listen pflegen und importieren', 'group' => 'Listen'],

            // Assets (Fuehrerscheine, Unterweisungen, ...)
            ['slug' => 'assets.view', 'name' => 'Assets ansehen', 'group' => 'Assets'],
            ['slug' => 'assets.manage', 'name' => 'Assets pflegen und importieren', 'group' => 'Assets'],

            // Webhooks
            ['slug' => 'webhooks.manage', 'name' => 'Webhooks verwalten', 'group' => 'Integrationen'],

            // Dokumente / OCR-Suche
            ['slug' => 'documents.search', 'name' => 'Dokumente durchsuchen', 'group' => 'Dokumente'],

            // Sharing-Links
            ['slug' => 'shares.create', 'name' => 'Dokumente per Link teilen', 'group' => 'Dokumente'],
            ['slug' => 'shares.manage_all', 'name' => 'Alle Freigaben verwalten', 'group' => 'Dokumente'],

            // Secrets-Vault (Tokens fuer HTTP-Knoten)
            ['slug' => 'secrets.manage', 'name' => 'Secrets verwalten', 'group' => 'Integrationen'],

            // E-Mail-Eingang (IMAP)
            ['slug' => 'mailboxes.manage', 'name' => 'E-Mail-Postfaecher verwalten', 'group' => 'Integrationen'],

            // System
            ['slug' => 'system.settings', 'name' => 'Systemeinstellungen', 'group' => 'System'],
            ['slug' => 'system.update', 'name' => 'System aktualisieren', 'group' => 'System'],
            ['slug' => 'system.health', 'name' => 'System-Health einsehen', 'group' => 'System'],
            ['slug' => 'system.backup', 'name' => 'Backups verwalten', 'group' => 'System'],
        ];

        foreach ($permissions as $p) {
            Permission::updateOrCreate(['slug' => $p['slug']], $p);
        }

        $roles = [
            [
                'slug' => 'admin',
                'name' => 'Administrator',
                'description' => 'Voller Zugriff auf alle Bereiche.',
                'is_system' => true,
                'permissions' => Permission::pluck('slug')->all(),
            ],
            [
                'slug' => 'workflow-designer',
                'name' => 'Workflow-Designer',
                'description' => 'Darf Workflows und Formulare entwerfen und veroeffentlichen.',
                'is_system' => true,
                'permissions' => [
                    'workflows.view', 'workflows.design', 'workflows.run',
                    'forms.view', 'forms.manage', 'users.view', 'roles.view',
                    'lists.view', 'lists.manage', 'assets.view', 'assets.manage',
                    'documents.search', 'shares.create',
                ],
            ],
            [
                'slug' => 'employee',
                'name' => 'Mitarbeiter',
                'description' => 'Standardrolle. Darf Workflows starten und Aufgaben bearbeiten.',
                'is_system' => true,
                'permissions' => ['workflows.run', 'forms.view'],
            ],
            [
                'slug' => 'auditor',
                'name' => 'Auditor',
                'description' => 'Darf das Audit-Log einsehen und die Integritaetskette pruefen.',
                'is_system' => true,
                'permissions' => ['audit.view', 'audit.verify', 'users.view', 'workflows.view'],
            ],
        ];

        foreach ($roles as $r) {
            $role = Role::updateOrCreate(
                ['slug' => $r['slug']],
                ['name' => $r['name'], 'description' => $r['description'], 'is_system' => $r['is_system']]
            );

            $permissionIds = Permission::whereIn('slug', $r['permissions'])->pluck('id');
            $role->permissions()->sync($permissionIds);
        }
    }
}
