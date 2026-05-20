# Single Sign-On (OIDC, Google, SAML)

Neben **Microsoft 365** (siehe [Microsoft 365](app:help.show?topic=m365))
unterstuetzt Open Workflow Engine drei weitere Identity-Provider — alle
ueber das Admin-UI konfigurierbar, ohne `.env`-Editieren.

> Konfiguration: **[Systemeinstellungen → SSO](app:admin.settings.sso)**

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

- `auth.oidc.login`, `auth.google.login`, `auth.saml.login` — Login OK
- `auth.oidc.provisioned`, ... — Neuer User wurde automatisch angelegt
- `auth.oidc.blocked`, ... — Login fuer deaktivierten Account verweigert
- `settings.sso.updated` — Konfiguration wurde geaendert
