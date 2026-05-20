<?php

namespace App\Services;

use App\Models\User;

/**
 * Findet/erstellt einen lokalen User-Record aus einem erfolgreichen
 * LDAP-Bind-Ergebnis. Wird vom LoginRequest aufgerufen, sobald die
 * Bind-Authentifizierung Erfolg hatte.
 */
class LdapUserProvisioner
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function findOrCreate(array $ldapResult): ?User
    {
        $dn = (string) ($ldapResult['dn'] ?? '');
        $email = strtolower((string) ($ldapResult['email'] ?? ''));
        $name = (string) ($ldapResult['name'] ?? $email);

        if ($dn === '') return null;

        $user = User::where('ldap_dn', $dn)->first();
        if (! $user && $email !== '') {
            $user = User::where('email', $email)->first();
        }

        $autoProvision = (bool) config('services.ldap.auto_provision', true);
        $defaultRole = (string) config('services.ldap.default_role', 'employee');

        if (! $user) {
            if (! $autoProvision) {
                return null;
            }
            if ($email === '') {
                // ohne E-Mail laesst sich kein konsistentes Profil anlegen
                return null;
            }
            $user = User::create([
                'name' => $name ?: $email,
                'email' => $email,
                'is_active' => true,
                'email_verified_at' => now(),
                'ldap_dn' => $dn,
            ]);
            $user->assignRole($defaultRole);
            $this->audit->log('auth.ldap.provisioned', $user, null, ['email' => $email, 'dn' => $dn],
                "Neuer Benutzer via LDAP angelegt: {$email}", $user->id);
            return $user;
        }

        $user->forceFill([
            'ldap_dn' => $user->ldap_dn ?: $dn,
            'name' => $user->name ?: $name,
        ])->save();

        return $user;
    }
}
