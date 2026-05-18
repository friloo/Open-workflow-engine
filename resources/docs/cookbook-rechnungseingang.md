# Cookbook: Rechnungseingang automatisieren

Dieses Rezept zeigt das volle Setup fuer „eingehende Rechnung per Mail
→ erkannte Kostenstelle → Genehmigung beim Verantwortlichen, sonst
Buchhaltung". Komplett ohne KI; KI ist nur optional als Fallback.

> **Zeitaufwand:** ca. 30 Minuten beim ersten Mal. Spaeter aenderst du
> nur noch die Liste.

## Voraussetzungen

- Rollen `buchhaltung` und `einkauf` existieren (Verwaltung -> Rollen
  -> *Neue Rolle*; jeweils mit Permission `workflows.run`).
- Mindestens ein Benutzer pro Rolle.
- Permission `documents.search` fuer alle, die Rechnungen sehen sollen.
- SMTP konfiguriert (Test-Button im Systemeinstellungen-Bereich).

## Schritt 1: Dokumenttyp anlegen

*Verwaltung -> Systemeinstellungen -> Dokumenttypen.*

Lege `Rechnung` an. Setze unter „Berechtigungen je Rolle" die
Sichtbarkeit fuer `buchhaltung` und `einkauf`.

## Schritt 2: Lookup-Liste „Kostenstellen"

*Stammdaten -> Listen -> Neue Liste.*

| Spalte | Rolle |
|---|---|
| `kostenstelle` | **Schluessel** |
| `bezeichnung` | sonstige |
| `verantwortlicher` | **Verantwortlich** (E-Mail des Verantwortlichen) |
| `eskalation` | Eskalation (optional) |

Liste pflegen — z. B. via *CSV-Import* (`Datei waehlen` →
Spalten-Mapping). Die Datei:

```csv
kostenstelle;bezeichnung;verantwortlicher;eskalation
K-2026-IT;IT;max.mustermann@example.com;cio@example.com
K-2026-MK;Marketing;eva.beispiel@example.com;cmo@example.com
```

Pruefen: `Stammdaten -> Listen -> Kostenstellen` sollte alle Eintraege
zeigen.

> **Wichtig:** Der Verantwortliche muss als Benutzer im System
> existieren — die E-Mail wird zur User-Resolution benutzt.

## Schritt 3: Felder-Schema fuer „Rechnung"

*Verwaltung -> Dokument-Schemas -> Rechnung -> Bearbeiten.*

Felder anlegen:

| Schluessel | Bezeichnung | Typ | Erkennung | Detail |
|---|---|---|---|---|
| `kostenstelle` | Kostenstelle | string | **Lookup-Liste** | Liste = `kostenstellen` |
| `rechnungsnummer` | Rechnungsnummer | string | Heuristik: Rechnungsnummer | — |
| `datum` | Rechnungsdatum | date | Heuristik: Datum (deutsch) | — |
| `betrag_brutto` | Brutto | currency | Heuristik: EUR-Betrag | — |
| `iban` | IBAN | iban | Heuristik: IBAN | — |
| `ustid` | USt-ID | string | Heuristik: USt-IdNr. (DE) | — |

**KI als Fallback** kannst du pro Feld optional aktivieren — wirkt
nur, wenn KI in den Systemeinstellungen konfiguriert ist. Default:
aus, alles laeuft regelbasiert.

*Speichern.* Wenn du schon Rechnungen im System hast, klick auf
*Jetzt neu indexieren* — extrahiert die Felder rueckwirkend.

## Schritt 4: Workflow „Rechnungseingang"

*Workflows -> Neuer Workflow.* Trigger **Manuell** (wird nicht ueber
Formular gestartet, sondern aus dem Postkorb oder direkt vom IMAP-
Postfach). *Speichern, dann Designer oeffnen.*

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

- **Empfaenger**: *Aus Liste nachschlagen*
- **Liste**: `Kostenstellen`
- **Schluessel aus Feld**: `doc.indexed_fields.kostenstelle`
- **Fallback-Rolle**: `Einkauf` *(Sicherheitsnetz fuer den Fall, dass
  die Liste den Code (noch) nicht kennt)*
- **Karenzzeit**: z. B. 3 Tage
- **Eskalation**: optional an die Rolle `einkauf`

### Genehmigung „Buchhaltung"

- **Empfaenger**: *Rolle* -> `Buchhaltung`
- **Karenzzeit**: z. B. 5 Tage

### Ende-Knoten

Eines reicht — beide Pfade enden dort.

*Speichern* legt eine neue Version an, *Aktivieren* schaltet den
Workflow scharf.

## Schritt 5: IMAP-Postfach „Rechnungen"

*Verwaltung -> E-Mail-Postfaecher -> Neues Postfach.*

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

*Test*-Button validiert die Verbindung. *Jetzt abrufen* fuehrt einen
einmaligen Lauf durch — gut zum ersten Pruefen.

Im Live-Betrieb laeuft `mail:fetch` alle 5 Minuten (siehe
Anleitung *E-Mail-Eingang*).

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
   - **Mit Kostenstelle** -> Genehmigung beim Eintrag aus der Liste
     (z. B. `max.mustermann@example.com`).
   - **Ohne** -> Genehmigung bei Rolle `Buchhaltung`.
8. Genehmiger bekommt Mail mit signiertem Genehmigen-/Ablehnen-Link
   (kein Login noetig).

## Was du noch tun kannst

- **Postkorb pruefen** (*Dokumente -> Postkorb*): Anhaenge, die der
  Auto-Workflow nicht direkt verarbeitet hat (z. B. wenn du das
  Postfach mal ohne Workflow konfiguriert hattest), kannst du dort
  manuell einem Workflow zuweisen.
- **Korrekturen** im Dokumenten-Detail: falls die Kostenstelle mal
  falsch erkannt wurde, editierst du das Feld direkt — wird im
  Audit-Log dokumentiert.
- **Liste aktualisieren**: neue Kostenstelle? Einfach den Eintrag
  ergaenzen. Beim naechsten Dokument greift sie sofort.

## Troubleshooting

- **Kein Workflow startet** -> Postfach unter *Verwaltung* pruefen:
  Spalte „Workflow" gesetzt? Postfach aktiv? Schau die Spalte „Status"
  und „last_error" an.
- **Workflow startet, aber Empfaenger ist leer** -> Lookup-Wert
  passt nicht zum Listen-Schluessel (z. B. Leerzeichen). Pruefe
  `attachments.indexed_fields.kostenstelle` in der Dokumenten-
  Detailansicht und den Listen-Eintrag.
- **Falsche Felder erkannt** -> Schema-Editor: Feld korrigieren oder
  Erkennungs-Typ aendern. Danach *Jetzt neu indexieren*.
- **Statistik unter** *Workflows -> Statistik -> Rechnungseingang*
  zeigt Engpaesse: ist die Bedingung zu eng? Sind viele Faelle ohne
  Kostenstelle? Liste erweitern.
