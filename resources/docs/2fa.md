# Zwei-Faktor-Anmeldung (TOTP)

OWE unterstuetzt TOTP-basierte Zwei-Faktor-Anmeldung (RFC 6238). Jeder
Benutzer entscheidet selbst, ob er sie aktiviert — es gibt **keine**
Pflicht-Vorgabe. Empfehlung: alle Konten mit `system.settings` oder
`shares.manage_all`.

## Aktivierung

*Topbar -> Profil-Menue -> Zwei-Faktor-Anmeldung*

1. QR-Code mit einer Authenticator-App scannen (Aegis, Google
   Authenticator, 1Password, ...). Alternativ den geheimen Schluessel
   manuell uebertragen.
2. Den 6-stelligen Code aus der App eingeben und auf **Aktivieren**
   klicken.
3. Die acht **Recovery-Codes** sicher verwahren (Passwort-Manager).
   Jeder Code funktioniert genau einmal — fuer den Fall, dass das
   Handy verloren geht.

## Login-Flow

Nach Passwort-Eingabe wird der Benutzer auf eine
**Zwei-Faktor-Verifizierung** Seite geleitet. Dort den aktuellen
6-stelligen Code eingeben — oder per *Stattdessen Recovery-Code
verwenden* einen Recovery-Code einloesen.

Solange der Code nicht bestaetigt wurde, ist der Benutzer **nicht**
eingeloggt — die Session traegt nur die User-ID des Halbschritts.

## Deaktivieren

Im gleichen Bereich. Erfordert das aktuelle Passwort als Bestaetigung.
Recovery-Codes werden gleichzeitig geloescht.

## Audit

- `auth.2fa.enabled` — beim Aktivieren
- `auth.2fa.disabled` — beim Deaktivieren
- `auth.2fa.verified` — bei korrektem Code im Login
- `auth.2fa.failed` — bei falschem Code
- `auth.2fa.recovery_used` — bei Einloesung eines Recovery-Codes
- `auth.2fa.recovery_regenerated` — bei Neu-Erzeugung der Codes
