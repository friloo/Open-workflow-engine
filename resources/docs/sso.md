# Anmeldung & SSO

Open Workflow Engine unterstuetzt fuenf externe Identity-Provider plus
lokales Passwort-Login — alle parallel aktivierbar, alle ueber das
Admin-UI konfigurierbar, ohne `.env`-Editieren.

| Provider | Wofuer | Login-Flow |
|----------|-------|------------|
| Microsoft 365 / Entra ID | Office-365-Tenant | Redirect |
| OpenID Connect | Keycloak, Authentik, Auth0, Okta, Zitadel | Redirect |
| Google Workspace | G-Workspace-Tenants | Redirect |
| SAML 2.0 | ADFS, PingFederate, Shibboleth | Redirect |
| LDAP / Active Directory | On-Prem AD/OpenLDAP/389DS | Direkter Bind |

> Konfiguration: **[Systemeinstellungen → Anmeldung & SSO](app:admin.settings.sso)**

## Microsoft 365 / Entra ID

Voraussetzung: App-Registration in Azure Portal mit:
- Redirect-URI = die im SSO-Formular angezeigte
  `https://deine-instanz/auth/m365/callback`
- API-Permissions: `User.Read` (delegated), optional `User.Read.All`
  (Application) fuer Benutzer-Sync mit Admin-Consent
- Client-Secret unter „Certificates & secrets"

Die SSO-Seite hat zwei Extra-Buttons fuer M365:

- **Verbindung testen** — holt ein App-Token und fragt Graph nach
  einem Test-User
- **Benutzer-Sync jetzt ausfuehren** — laedt alle aktiven Tenant-User
  in OWE (mit Vorgesetzten-Beziehung), entsprechend dem Cronjob
  `php artisan m365:sync-users`

## OpenID Connect (generisch)

Funktioniert mit jedem OIDC-konformen Provider:

- **Keycloak**, **Authentik**, **Zitadel** (Self-hosted)
- **Auth0**, **Okta**, **OneLogin** (SaaS)
- Jeder eigene OIDC-Server mit Discovery-Endpoint

### Anbinden

1. Beim Provider eine Anwendung anlegen mit:
   - Redirect-URI = die im SSO-Formular angezeigte
     `https://deine-instanz/auth/oidc/callback`
   - Scopes: `openid email profile`
2. Issuer-URL, Client-ID, Client-Secret in
   **[SSO-Einstellungen](app:admin.settings.sso)** eintragen
3. Discovery-Dokument wird automatisch von
   `<issuer>/.well-known/openid-configuration` gezogen + 1h gecached
4. „OIDC-Anmeldung aktivieren" anhaken, speichern, fertig

### Beispiele

**Keycloak (Realm `main`):**

```
Issuer:    https://keycloak.example.com/realms/main
Client-ID: owe-app
```

**Authentik:**

```
Issuer:    https://authentik.example.com/application/o/owe/
Client-ID: <aus Authentik-UI>
```

**Auth0:**

```
Issuer:    https://your-tenant.eu.auth0.com
Client-ID: <aus Auth0-Dashboard>
```

### E-Mail-Verifizierung

Standardmaessig akzeptiert OWE nur Konten mit
`email_verified=true` im UserInfo-Claim. Wenn dein IdP das nicht
liefert, kommt eine Fehlermeldung — Provider-seitig bitte
verifizierte Mails erzwingen.

## Google Workspace

Fuer Unternehmen mit Google Workspace.

### Anbinden

1. In der **Google Cloud Console** ein OAuth-2.0-Client erstellen
   (Typ: Webanwendung)
2. Autorisierte Redirect-URI:
   `https://deine-instanz/auth/google/callback`
3. Client-ID + Secret in
   **[SSO-Einstellungen](app:admin.settings.sso)** eintragen
4. Optional **Hosted-Domain** = deine Workspace-Domain
   (z. B. `firma.de`) — dann koennen nur Konten dieser Domain sich
   anmelden

### Sicherheits-Hinweis Hosted-Domain

Der `hd`-Parameter ist ein **Hinweis** an Google's Login-UI, kein
hartes Sicherheits-Feature. Deshalb prueft OWE **zusaetzlich** nach
dem Callback, ob die zurueckgegebene E-Mail wirklich aus dieser
Domain stammt — wenn nicht, wird die Anmeldung verweigert.

## SAML 2.0

Fuer Enterprise-IdPs (ADFS, PingFederate, Shibboleth, ...).

### Anbinden

1. Service-Provider-Metadaten von OWE herunterladen unter
   `https://deine-instanz/auth/saml/metadata` und beim IdP importieren
2. Vom IdP brauchen wir:
   - **Entity-ID**
   - **SingleSignOn-URL** (HTTP-Redirect-Binding)
   - **X.509-Zertifikat** (PEM-Format)
3. In **[SSO-Einstellungen](app:admin.settings.sso)** eintragen
4. Attribute-Mapping einstellen — Defaults:
   - E-Mail: Attribut `email`
   - Anzeigename: Attribut `displayName`

### Signaturen

Empfohlen: **„Signierte Assertions verlangen"** anhaken. Das
verhindert, dass jemand ohne Zugriff auf den IdP-Schluessel eine
gueltige Antwort faelschen kann.

### Troubleshooting

- **„SAML-Antwort ungueltig"** — meist falsche Uhrzeit (Server-Clock
  driftet) oder NameIDFormat passt nicht. Logs unter
  `storage/logs/laravel.log` checken.
- **Zertifikat-Format** — PEM mit oder ohne
  `-----BEGIN CERTIFICATE-----`-Zeilen wird automatisch normalisiert.

## LDAP / Active Directory (direkt)

Im Unterschied zu OIDC/SAML gibt es bei LDAP **keinen Redirect**. Der
User tippt seinen AD-Login plus Passwort direkt in die OWE-Login-Maske
ein und OWE bindet sich damit am LDAP-Server.

### Voraussetzung

PHP-LDAP-Extension auf dem Server:

```
apt install php8.2-ldap        # Debian/Ubuntu
dnf install php-ldap            # Fedora/RHEL
```

Ohne Extension zeigt die SSO-Settings-Seite einen Hinweis und LDAP
bleibt deaktiviert.

### Konfiguration

In **[SSO-Einstellungen](app:admin.settings.sso)** unter „LDAP / Active
Directory":

| Feld | Beispiel (Active Directory) |
|------|-----------------------------|
| Host | `ldap.firma.local` (oder `ldaps://ldap.firma.local`) |
| Port | `389` (LDAP) oder `636` (LDAPS) |
| StartTLS | an, wenn Port 389 |
| Base-DN | `DC=firma,DC=local` |
| Service-Account DN | `CN=svc-owe,OU=Service,DC=firma,DC=local` |
| Service-Passwort | Passwort des Service-Accounts |
| User-Filter | `(&(objectClass=user)(sAMAccountName={username}))` |
| E-Mail-Attribut | `mail` |
| Name-Attribut | `displayName` |

Fuer **OpenLDAP / 389DS / Samba 4** entsprechend:

```
Filter: (&(objectClass=inetOrgPerson)(uid={username}))
```

### So laeuft der Login ab

1. User tippt `mmustermann` (AD-Login) + Passwort
2. OWE verbindet sich als Service-Account zum LDAP
3. Sucht nach `(&(objectClass=user)(sAMAccountName=mmustermann))`
4. Bindet sich dann als gefundener User mit dessen Passwort —
   das ist die eigentliche Authentifizierung
5. Mappt LDAP-Mail auf lokalen User (oder legt neu an, wenn
   Auto-Provisioning an ist)

### Test-Funktion

Auf der SSO-Seite gibt's eine **„Verbindung testen"**-Karte. Du
gibst einen echten AD-User + Passwort ein, OWE meldet zurueck:

- Erfolg + den gefundenen DN + die Mail-Adresse
- ODER eine Fehlermeldung („Service-Bind fehlgeschlagen", „Filter
  ist nicht eindeutig", ...)

Das Passwort wird nirgendwo gespeichert.

### Sicherheits-Hinweise

- **LDAPS oder StartTLS** verwenden — sonst geht das User-Passwort
  im Klartext ueber's Netz.
- **Service-Account** mit minimalen Rechten anlegen (nur Lese-Recht
  auf die OU mit den User-Objekten).
- **LDAP-Injection** ist verhindert: alle User-Eingaben werden
  RFC4515-eskapiert, bevor sie in den Filter eingesetzt werden.

## Auto-Provisioning

Bei allen Providern ist „Neue Benutzer beim ersten Login automatisch
anlegen" **per Default an**. Vorteil: keine manuelle Pflege.

Wenn deaktiviert: nur Benutzer, die ein Admin schon angelegt hat,
koennen sich per SSO anmelden — der IdP-Identifier wird beim ersten
Login mit dem existierenden Konto verknuepft.

## Mehrere Provider gleichzeitig

Du kannst beliebig viele Provider parallel aktivieren. Auf der
Login-Seite erscheint pro aktivem Provider ein Button. Das normale
Passwort-Login bleibt nebenher verfuegbar (lokale Service-Accounts).

## Audit

Jeder SSO-Login wird im Audit-Log festgehalten:

- `auth.oidc.login`, `auth.google.login`, `auth.saml.login`,
  `auth.ldap.login` — Login OK
- `auth.oidc.provisioned`, ... — Neuer User wurde automatisch angelegt
- `auth.oidc.blocked`, ... — Login fuer deaktivierten Account verweigert
- `settings.sso.updated` — Konfiguration wurde geaendert
- `settings.ldap.test_ok` / `settings.ldap.test_failed` — Test-Verbindung
