# Erste Schritte als Admin

Diese Seite fuehrt dich nach der frischen Installation durch die wichtigsten
Schritte. Wenn du fertig bist, ist deine OWE bereit fuer den ersten echten
Workflow.

> [!TIP]
> Auf dem Dashboard siehst du eine **„Erste Schritte"-Karte** mit Fortschritts-
> balken. Sie haengt jeden Punkt automatisch ab, sobald du ihn erledigt hast,
> und verschwindet komplett, wenn alles eingerichtet ist.

## 1. Anmelden

Melde dich mit dem im Installer angelegten Admin-Konto an. Falls du
das Passwort vergessen hast, hilft das `php artisan tinker`-CLI auf dem
Server — siehe [Administration](app:help.show?topic=admin).

## 2. SMTP einrichten

Damit Workflow-Benachrichtigungen, Erinnerungen und Mail-Genehmigungen
versendet werden koennen, brauchst du einen Mail-Server.

1. Gehe zu **[Einstellungen → Mail-Versand](app:admin.settings.mail)**.
2. Trage Host, Port, Encryption (meist `STARTTLS` Port 587), Benutzer
   und Passwort deines SMTP-Servers ein.
3. Setze **Absender-Adresse** und **Absender-Name** — das ist, was deine
   Empfaenger sehen.
4. Speichern.
5. Schick dir rechts eine **Test-Mail** und pruefe deinen Posteingang.

> [!NOTE]
> Wenn du noch keinen SMTP-Server hast, kannst du Transport auf **„Log"**
> stellen — Mails werden dann nur ins Laravel-Log geschrieben. Praktisch zum
> Testen, aber natuerlich nichts fuer Produktion.

## 3. (Optional) Single Sign-On

Du kannst die Anmeldung ueber einen externen Identity-Provider laufen
lassen — Microsoft 365 / Entra ID, OpenID Connect (Keycloak, Authentik,
Auth0, Okta), Google Workspace, SAML 2.0 oder LDAP/Active Directory.

Details: [Anmeldung & SSO](app:help.show?topic=sso).

Kurzform: **[Einstellungen → Anmeldung & SSO](app:admin.settings.sso)**,
Provider auswaehlen, Credentials eintragen, Verbindung testen,
aktivieren — Login-Button erscheint danach automatisch auf der
Anmeldeseite.

## 4. Benutzer anlegen

Drei Wege:

| Weg | Wann | Wo |
|-----|------|-----|
| **Einzeln** | wenige Benutzer, manuell | [Benutzer → Neu](app:admin.users.create) |
| **CSV-Import** | grosse Liste aus HR-System | [Benutzer → CSV-Import](app:admin.users.import.show) |
| **SSO-Auto-Provision** | IdP ist Single Source of Truth | [Einstellungen → Anmeldung & SSO](app:admin.settings.sso) |

Beim CSV-Import muessen die Spalten den Feld-Keys deiner Custom-Fields
entsprechen. **Vorgesetzte** verknuepfst du ueber `supervisor_email` —
OWE loest die Email zu einer User-ID auf.

> [!IMPORTANT]
> Lege immer mindestens **zwei** Admin-User an. Wenn der erste Admin
> Urlaub hat oder das Passwort vergisst, kommst du sonst nicht mehr rein.

## 5. Rollen anpassen

OWE startet mit vier Standard-Rollen:

- **admin** — alles
- **workflow-designer** — entwirft Workflows, Listen, Assets
- **employee** — startet Workflows, bearbeitet eigene Aufgaben
- **auditor** — sieht das Audit-Log

In **[Rollen & Rechte](app:admin.roles.index)** kannst du eigene anlegen.
Beim Bearbeiten einer Rolle siehst du in **einem Fenster**:

1. Permissions (welche Aktionen darf sie)
2. Dokument-Archive (welche Typen sieht sie in der Suche)
3. Listen-Zugriff (lesen / bearbeiten)

> [!TIP]
> Eine Rolle muss **nicht** generellen Zugriff auf ein Dokument-Archiv
> haben, damit ein Mitglied eine Aufgabe darauf bearbeiten kann. Wenn
> jemand per Workflow eine Rechnung zur Genehmigung bekommt, sieht er
> die Rechnung im Aufgaben-Kontext — auch ohne Archive-Permission.

## 6. Dokument-Archive (Typen) anlegen

Archive sind die "Schubladen", in denen klassifizierte Dokumente landen
(z. B. Rechnungen, Vertraege, Fuehrerscheine).

1. Gehe zu **[Einstellungen → Dokumente](app:admin.settings.documents)**.
2. Klick "+ Typ" und lege deine Archive an — etwa `Rechnung`, `Vertrag`,
   `Fuehrerschein`.
3. Speichern.

Optional: in **[Dokument-Schemas](app:admin.document_schemas.index)**
definierst du **welche Indexfelder** jeder Typ hat (z. B. fuer Rechnungen:
`rechnungsnummer`, `betrag`, `kostenstelle`). Diese Felder werden bei
OCR-Verarbeitung extrahiert und sind in Workflows als Platzhalter
verfuegbar.

## 7. Datenquellen anbinden

Damit nicht jedes Dokument von Hand hochgeladen werden muss:

- **[E-Mail-Postfach](app:admin.mailboxes.index)** — IMAP-Konto, OWE holt
  alle paar Minuten neue Mails und legt Anhaenge in den Postkorb.
- **[Folder-Inbox](app:admin.folder-inboxes.index)** — ein Ordner auf dem
  Server (z. B. von einem Multifunktionsgeraet befuellt). OWE indexiert
  alles Neue automatisch.
- **[Eingehende Webhooks](app:admin.incoming-webhooks.index)** — externe
  Systeme triggern Workflows per HTTP-POST.

## 8. Erste Liste

Listen sind Lookup-Tabellen — typisch fuer Kostenstellen, Lieferanten,
Standorte. Sie sind die Bruecke zwischen einem Dokumentfeld und der
Person, die etwas damit machen soll.

1. **[Listen → Neue Liste](app:lists.index)**.
2. Mindestens eine Spalte als **Schluessel** markieren.
3. Eine Spalte als **Verantwortlich** (Email) — wird im Workflow fuer
   "Empfaenger aus Liste nachschlagen" verwendet.

Beispiel "Kostenstellen":

| Schluessel | Verantwortlich (Email) | Eskalation (Email) |
|-----------|------------------------|---------------------|
| K-100 | anna@example.org | leitung@example.org |
| K-200 | bernd@example.org | leitung@example.org |

## 9. Erster Workflow

1. **[Workflows → Neuer Workflow](app:workflows.index)**.
2. Trigger waehlen — meistens „Manuell" oder „Formular" am Anfang.
3. Im Designer Knoten reinziehen: **Start → Genehmigung → Ende**.
4. Beim Genehmigungs-Knoten Empfaenger einstellen (Rolle, einzelne
   Person, Vorgesetzter, oder „Aus Liste nachschlagen").
5. Speichern (legt eine **Version** an) und **aktivieren**.

> [!TIP]
> Im [Designer](app:workflows.index) findest du eine **Knoten-Palette**
> nach Kategorien sortiert. Hover auf einen Knoten → Tooltip mit
> Erklaerung. Ziehen, ablegen, Verbindungen ziehen vom kleinen Punkt
> rechts zum naechsten Knoten.

## 10. End-to-End-Beispiel

Wenn du sehen willst, wie das alles zusammenspielt, geh die
**[Cookbook-Anleitung Rechnungseingang](app:help.show?topic=cookbook-rechnungseingang)**
durch. Sie zeigt: IMAP-Postfach → OCR → Kostenstellen-Routing →
Genehmigung → Archivierung.

> [!NOTE]
> Wenn etwas nicht klappt: in **[System-Health](app:admin.health.index)**
> siehst du, was nicht erreichbar / nicht konfiguriert ist. Und im
> **[Audit-Log](app:admin.audit.index)** steht jede Aktion mit Zeitstempel.
