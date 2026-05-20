# DSGVO: Auskunft und Vergessenwerden

Zwei Standard-Anforderungen der DSGVO sind ueber das Admin-UI direkt
abgedeckt:

- **Art. 15 (Auskunft)**: Eine Person fragt, welche personenbezogenen
  Daten OWE ueber sie speichert. Du gibst ein ZIP mit allen relevanten
  Datensaetzen heraus.
- **Art. 17 (Vergessenwerden)**: Eine Person fordert die Loeschung
  ihrer Daten. Du anonymisierst den User — Workflow-Historie bleibt
  (gesetzliche Aufbewahrung), aber Name + Email + Custom-Felder werden
  ersetzt.

> [!IMPORTANT]
> Beide Aktionen muessen **dokumentiert** sein. OWE schreibt automatisch
> Audit-Log-Eintraege (`gdpr.access_request` und `gdpr.anonymization`)
> mit Before/After-Snapshots — das ist der Nachweis fuer deinen
> Datenschutz-Verantwortlichen.

## Wo finde ich das?

[Admin → DSGVO](app:admin.gdpr.index) — sichtbar fuer User mit
Permission `system.settings`.

## Auskunft (Art. 15)

1. Email-Adresse der betroffenen Person eingeben.
2. **Auskunft als ZIP herunterladen**.
3. ZIP an die Person aushaendigen (per verschluesselter Mail, sicherem
   Cloud-Link oder Briefumschlag — *nicht* einfach per Klartext-Mail).

Im ZIP enthalten:

```
DSGVO-Auskunft-max_example_de-2026-05-19.zip
├── README.txt           Erstellungs-Metadaten + Inhalts-Liste
└── export.json          Alle Daten gruppiert nach Tabelle
```

`export.json` ist ein lesbares JSON mit:

- **subject_user**: User-Profil (Name, Email, Custom-Fields, Rollen)
- **workflow_instances**: Alle vom User gestarteten Vorgaenge mit
  Status, Form-Daten, Zeitstempeln
- **workflow_steps**: Alle Steps wo der User zugewiesen war oder
  entschieden hat (Decision + Comment)
- **comments**: Alle Vorgangs-Kommentare des Users
- **attachments**: Liste der vom User hochgeladenen Dateien
  (Metadata — keine Binaer-Daten im JSON)
- **audit_log**: Alle Audit-Eintraege mit User-ID (bis 5000 Stueck)
- **saved_searches**: Gespeicherte Filter-Kombinationen
- **notification_preferences**: Welche Benachrichtigungen der User
  aktiviert hat

Was **NICHT** im ZIP ist:
- Die eigentlichen PDF-/Bilddateien — die haengen meist an Belegen
  und sind nicht eindeutig personenbezogen.
- Daten anderer User die zufaellig denselben Vorgang beruehrt haben.

> [!TIP]
> Wenn die Person auch eigene Dokumente angefordert hat, exportier
> sie ueber [Dokumente](app:documents.index) mit Filter
> „uploader = subject@example.com" (CSV-Export + Bulk-Download).

Audit-Eintrag: `gdpr.access_request` mit der Email + Datensummary.

## Vergessenwerden (Art. 17)

Aktion **nicht rueckgaengig zu machen**. Drei Eingaben verlangt:

1. **Email** der betroffenen Person
2. **Grund** (Pflicht-Feld, max 500 Zeichen) — z. B.
   „Loeschungs-Antrag der Person vom 19.05.2026"
3. **Sicherheits-Bestaetigung**: tippe genau das Wort
   `ANONYMISIEREN` als Sicherheitsbarriere

Was passiert dann technisch:

| Feld | Vorher | Nachher |
|---|---|---|
| name | Max Mustermann | Anonymisiert #42 |
| email | max@example.com | anonymized-42@deleted.local |
| password | (Hash) | Random-Hash (Login unmoeglich) |
| department | IT | leer |
| custom_fields | { … } | leer |
| supervisor_id | 7 | null |
| 2FA-Secret + Recovery-Codes | gesetzt | leer |
| is_active | true | false |
| email_notifications_enabled | true | false |
| Rollen-Zuweisungen | [admin, employee] | keine |
| API-Tokens | (Liste) | geloescht |
| Sessions | aktiv | geloescht |
| Saved Searches | … | geloescht |
| Notification-Praeferenzen | … | geloescht |

Was **bleibt** (gesetzlich begruendete Aufbewahrung gemaess
§ 147 AO, 10 Jahre):

- Workflow-Instanzen + Step-Historie
  (die FK `started_by`, `completed_by` zeigt weiter auf den User —
  jetzt anonymisiert; sichtbar nur noch als „Anonymisiert #42")
- Vom User hochgeladene Belege (z. B. Rechnungen)
- Audit-Log-Eintraege (der User bleibt als `Anonymisiert #42`
  referenziert — Rechtsverbindlichkeit der Hashkette bleibt erhalten)

Audit-Eintrag: `gdpr.anonymization` mit Before/After-Snapshot,
inkl. dem angegebenen Grund.

## Praktischer Ablauf

So sieht der typische Workflow aus, wenn jemand schreibt
„Ich moechte meine Daten geloescht haben":

1. **Identitaet pruefen** (Antwort an die Mail-Adresse mit Rueckfrage —
   beim ersten Mal ist es immer ein Test, ob die Person wirklich der
   Inhaber ist).
2. **Auskunft generieren**: Admin → DSGVO → Email eintragen → ZIP.
3. **ZIP an die Person liefern**.
4. Bei expliziter Loeschungs-Anfrage: **Anonymisieren** ueber das
   Form.
5. **Audit-Log-Eintrag** als PDF exportieren (Audit-Log-Seite filtert
   nach `gdpr.*` events) und in den Datenschutz-Ordner deiner Org.

## Hinweise

- **Backup-Loeschung**: alte Backups enthalten noch die nicht-anonymisierten
  Daten. Nach den Retention-Regeln deines Backups laufen die irgendwann
  raus (typisch 30-90 Tage); das ist DSGVO-konform, sofern die Aufbewahrungs-
  fristen kurz sind.
- **Search-Index (MeiliSearch)**: Wenn aktiviert, sollte nach einer
  Anonymisierung `php artisan search:reindex` laufen, damit der externe
  Index aktualisiert wird.
- **Mehrere Email-Adressen**: ein Mensch kann mehrere User-Accounts in
  OWE haben. Pro Email einzeln durchziehen.

## Was OWE *nicht* automatisch macht

- **Mehrstufige Approval-Pruefung der Loeschung**: aktuell loest jeder
  System-Settings-Admin das direkt aus. Wer 4-Augen-Prinzip will, baut
  einen Workflow um die Anonymisierung herum (Approval → bei OK ruft
  Workflow den HTTP-Knoten gegen das interne Endpoint auf). Geplant
  als Standard-Workflow-Template.
- **Auto-Auskunft per Self-Service** (User klickt selbst „Meine Daten
  exportieren"): kommt voraussichtlich als naechstes Feature.
- **Loeschung der Backups** muss manuell oder ueber Backup-Tool-eigene
  Retention erfolgen.
