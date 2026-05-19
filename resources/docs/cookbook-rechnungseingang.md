# Cookbook: Rechnungseingang automatisieren

End-to-End-Rezept: Rechnung kommt per Mail rein → wird klassifiziert,
Kostenstelle wird per Lookup erkannt → Genehmigung beim Verantwortlichen,
sonst beim Einkauf. Komplett ohne KI; KI ist nur optional als Fallback.

> [!TIP]
> Zeitaufwand: ca. 30 Minuten beim ersten Mal. Spaeter aenderst du nur
> noch die Kostenstellen-Liste, wenn neue Kostenstellen dazukommen.

## Voraussetzungen

Bevor du loslegst, stelle sicher:

- Rollen `buchhaltung` und `einkauf` existieren (in
  [Rollen & Rechte](app:admin.roles.index) → *Neue Rolle*). Beide
  brauchen die Permission `workflows.run`.
- Mindestens ein Benutzer pro Rolle.
- Permission `documents.search` fuer alle, die Rechnungen sehen sollen.
- SMTP konfiguriert (Test-Button in
  [Einstellungen → Mail-Versand](app:admin.settings.mail)).

> [!NOTE]
> Wenn du diese Rollen anlegst, kannst du **direkt im Rollen-Editor** den
> sichtbaren Doku-Archive-Zugriff vergeben — siehe Schritt 1 unten.

## Schritt 1: Dokument-Archive anlegen

[Einstellungen → Dokumente](app:admin.settings.documents) öffnen.

Lege das Archiv `Rechnung` an. Speichern.

Jetzt zu [Rollen & Rechte](app:admin.roles.index) und beide Rollen
nacheinander bearbeiten: in der „Sichtbare Dokument-Archive"-Sektion
einen Haken bei **Rechnung** setzen.

> [!IMPORTANT]
> Auch Benutzer, die **nur eine Aufgabe** auf einer Rechnung bekommen,
> koennen das PDF im Kontext der Aufgabe oeffnen — auch ohne diese
> Archive-Permission. Die Permission ist nur fuer die generelle
> Dokumenten-Suche relevant.

## Schritt 2: Lookup-Liste „Kostenstellen"

[Listen → Neue Liste](app:lists.index) anlegen mit Name `Kostenstellen`.

Spalten:

| Spalte | Rolle |
|---|---|
| `kostenstelle` | **Schluessel** |
| `bezeichnung` | sonstige |
| `verantwortlicher` | **Verantwortlich** (E-Mail des Verantwortlichen) |
| `eskalation` | Eskalation (optional) |

Liste pflegen — am bequemsten per **CSV-Import** (Button oben rechts in
der Listen-Detailansicht):

```csv
kostenstelle;bezeichnung;verantwortlicher;eskalation
K-2026-IT;IT;max.mustermann@example.com;cio@example.com
K-2026-MK;Marketing;eva.beispiel@example.com;cmo@example.com
K-2026-VK;Vertrieb;sales@example.com;cso@example.com
```

> [!WARNING]
> Der Verantwortliche muss als Benutzer im System existieren — die
> E-Mail wird zur User-Resolution benutzt. Wenn der Eintrag auf eine
> nicht existierende Mail zeigt, faellt das Routing auf die in Schritt 4
> definierte **Fallback-Rolle** zurueck.

## Schritt 3: Felder-Schema fuer „Rechnung"

[Dokument-Schemas](app:admin.document_schemas.index) → `Rechnung` →
*Bearbeiten*.

Felder anlegen:

| Schluessel | Bezeichnung | Typ | Erkennung | Detail |
|---|---|---|---|---|
| `kostenstelle` | Kostenstelle | string | **Lookup-Liste** | Liste = `Kostenstellen` |
| `rechnungsnummer` | Rechnungsnummer | string | Heuristik: Rechnungsnummer | — |
| `datum` | Rechnungsdatum | date | Heuristik: Datum (deutsch) | — |
| `betrag_brutto` | Brutto | currency | Heuristik: EUR-Betrag | — |
| `iban` | IBAN | iban | Heuristik: IBAN | — |
| `ustid` | USt-ID | string | Heuristik: USt-IdNr. (DE) | — |

> [!TIP]
> **KI als Fallback** kannst du pro Feld optional aktivieren — wirkt nur,
> wenn KI in den [Einstellungen → KI](app:admin.settings.ai)
> konfiguriert ist. Default: aus, alles laeuft regelbasiert.

Speichern. Wenn du schon Rechnungen im System hast, klick auf **Jetzt
neu indexieren** — das extrahiert die Felder rueckwirkend.

## Schritt 4: Workflow „Rechnungseingang"

[Workflows → Neuer Workflow](app:workflows.index) mit Name
`Rechnungseingang`. Trigger **Manuell** waehlen (wird nicht ueber ein
Formular gestartet, sondern aus dem Postkorb oder direkt vom IMAP-
Postfach in Schritt 5). Speichern, dann Designer oeffnen.

Knoten ziehen:

```
[Start] -> [Bedingung: kostenstelle vorhanden?]
                |
                +-- ja --> [Genehmigung: Lookup-Empfaenger] -> [Ende: completed]
                |
                +-- sonst -> [Genehmigung: Rolle Buchhaltung] -> [Ende: completed]
```

### Bedingungs-Knoten

- **Feld**: `doc.indexed_fields.kostenstelle`
- **Operator**: `nicht leer`

Im Branch „ja" geht's zum Lookup-Empfaenger, im Sonst-Branch zur
Buchhaltung.

### Genehmigung „Lookup-Empfaenger"

- **Empfaenger-Typ**: *Aus Liste nachschlagen*
- **Liste**: `Kostenstellen`
- **Schluessel aus Feld**: `doc.indexed_fields.kostenstelle`
- **Fallback-Rolle**: `Einkauf` — Sicherheitsnetz fuer den Fall, dass
  die Liste den Code (noch) nicht kennt.
- **Karenzzeit**: z. B. 3 Tage
- **Eskalation**: optional an die Rolle `einkauf`

### Genehmigung „Buchhaltung"

- **Empfaenger-Typ**: *Rolle* → `Buchhaltung`
- **Karenzzeit**: z. B. 5 Tage

### Ende-Knoten

Eines reicht — beide Pfade enden dort.

> [!TIP]
> Bevor du den Workflow aktivierst, nutze die
> **[Workflow-Simulation](app:help.show?topic=simulation)** — Trockenlauf
> mit Testdaten. So siehst du ob die Bedingung wirklich greift und der
> Lookup funktioniert.

Speichern legt eine neue Version an, **Aktivieren** schaltet den
Workflow scharf.

## Schritt 5: IMAP-Postfach „Rechnungen"

[E-Mail-Postfaecher → Neues Postfach](app:admin.mailboxes.index)
anlegen.

| Feld | Wert |
|---|---|
| Name | `Rechnungseingang` |
| Host | z. B. `imap.example.com` |
| Port | `993` |
| Verschluesselung | `SSL` |
| Zertifikat pruefen | an |
| Benutzer | `rechnungen@example.com` |
| Passwort | (wird verschluesselt gespeichert) |
| Ordner | `INBOX` |
| **Dokumenttyp** | `Rechnung` |
| **Workflow** | `Rechnungseingang` |
| Verarbeitete Mails verschieben | an, Zielordner `Verarbeitet` |

**Test**-Button validiert die Verbindung. **Jetzt abrufen** fuehrt einen
einmaligen Lauf durch — gut zum ersten Pruefen.

Im Live-Betrieb laeuft `mail:fetch` alle 5 Minuten — siehe
[E-Mail-Eingang](app:help.show?topic=mailbox).

## Was jetzt automatisch passiert

1. Mail trifft im IMAP-Postfach ein.
2. Cron-Lauf zieht sie, legt einen `mailbox_messages`-Eintrag an.
3. PDF-Anhang wird mit `document_type = Rechnung` archiviert (SHA-256,
   Audit).
4. OCR extrahiert den Text.
5. Schema „Rechnung" laeuft: `kostenstelle` per Lookup, andere Felder
   per Heuristik.
6. Workflow „Rechnungseingang" startet, der Anhang wird daran
   gehaengt.
7. Bedingung prueft `doc.indexed_fields.kostenstelle`:
   - **Mit Kostenstelle** → Genehmigung beim Eintrag aus der Liste
     (z. B. `max.mustermann@example.com`).
   - **Ohne** → Genehmigung bei Rolle `Buchhaltung`.
8. Genehmiger bekommt Mail mit signiertem
   [Genehmigen-/Ablehnen-Link](app:help.show?topic=mail-approval) (kein
   Login noetig).

## Was du noch tun kannst

- **[Postkorb pruefen](app:documents.inbox)**: Anhaenge, die der
  Auto-Workflow nicht direkt verarbeitet hat (z. B. wenn du das
  Postfach mal ohne Workflow konfiguriert hattest), kannst du dort
  manuell einem Workflow zuweisen.
- **Korrekturen** im Dokumenten-Detail: falls die Kostenstelle mal
  falsch erkannt wurde, editierst du das Feld direkt — wird im
  [Audit-Log](app:admin.audit.index) dokumentiert.
- **Liste aktualisieren**: neue Kostenstelle? Einfach den Eintrag in
  der [Kostenstellen-Liste](app:lists.index) ergaenzen. Beim naechsten
  Dokument greift sie sofort.

## Troubleshooting

> [!WARNING]
> **Kein Workflow startet** → Postfach unter
> [E-Mail-Postfaecher](app:admin.mailboxes.index) pruefen: Spalte
> „Workflow" gesetzt? Postfach aktiv? Schau die Spalte „Status" und
> „last_error" an.

> [!WARNING]
> **Workflow startet, aber Empfaenger ist leer** → Lookup-Wert passt
> nicht zum Listen-Schluessel (z. B. Leerzeichen oder Gross-/Klein-
> schreibung). Pruefe `attachments.indexed_fields.kostenstelle` in der
> Dokumenten-Detailansicht und den Listen-Eintrag.

> [!NOTE]
> **Falsche Felder erkannt** → Schema-Editor: Feld korrigieren oder
> Erkennungs-Typ aendern. Danach „Jetzt neu indexieren".

> [!TIP]
> **Statistik im Blick** → [Workflow-Statistik →
> Rechnungseingang](app:workflows.stats.index) zeigt Engpaesse: ist die
> Bedingung zu eng? Sind viele Faelle ohne Kostenstelle? Liste
> erweitern.
