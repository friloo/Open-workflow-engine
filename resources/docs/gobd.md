# GoBD-Konformitaet & Verfahrensdokumentation

GoBD = **Grundsaetze zur ordnungsmaessigen Fuehrung und Aufbewahrung
von Buechern, Aufzeichnungen und Unterlagen in elektronischer Form
sowie zum Datenzugriff** (BMF-Schreiben vom 28.11.2019).

Wer in Deutschland steuerlich relevante Belege elektronisch archiviert
und/oder Geschaeftsprozesse digitalisiert, **muss** die GoBD einhalten.
Diese Seite erklaert welche OWE-Features welche Anforderungen
abdecken und was du selbst noch tun musst.

> [!IMPORTANT]
> OWE ist **technisch GoBD-tauglich**. Eine GoBD-**Konformitaet
> deiner Organisation** entsteht aber erst durch eine eigene
> **Verfahrensdokumentation**, die den Einsatz von OWE in deinem
> Betriebsablauf beschreibt. OWE liefert die Bausteine, du
> dokumentierst den Einsatz.

## Die 6 GoBD-Kernanforderungen + wie OWE sie erfuellt

### 1. Nachvollziehbarkeit und Nachpruefbarkeit (Rn. 30 ff.)

> Geschaeftsvorfaelle muessen sich in ihrer Entstehung und Abwicklung
> verfolgen lassen.

**OWE deckt das ab durch:**

- **Audit-Log** mit Hash-Kette (jeder Eintrag enthaelt einen SHA-256
  ueber den vorherigen — Manipulation bricht die Kette und faellt
  beim `audit:verify`-Run sofort auf).
- **Workflow-Verlauf**: pro Vorgang sieht man Schritt-fuer-Schritt
  wer wann wie entschieden hat, inkl. Kommentare und Zusatzfelder.
- **Versionierung** aller Dokumente: jede Aenderung legt eine neue
  Version an, alte Versionen bleiben dauerhaft erhalten.
- **Hash-Verify**: per `php artisan attachments:verify-all` kannst
  du jederzeit alle Anhaenge gegen ihren gespeicherten SHA-256
  pruefen.

### 2. Vollstaendigkeit (Rn. 36)

> Alle Geschaeftsvorfaelle muessen erfasst sein.

**OWE deckt das ab durch:**

- **Eingangs-Kanaele** (Mail, Folder, Webhook, manueller Upload)
  fangen alles ein. Bei IMAP- und Folder-Inbox werden Mails / Files
  beim ersten Lauf erfasst und in den Postkorb gelegt.
- **Duplikat-Erkennung** verhindert doppelte Buchung derselben
  Datei — aber lehnt auch keine "echten" Doppel-Belege ab.
- **Postkorb** zeigt unklassifizierte Eingaenge die noch nicht
  bearbeitet wurden — keine versteckten Backlogs.

**Was du tust**: alle eingangsrelevanten Kanaele (E-Mail-Konten,
Scan-Ordner) anbinden. Wenn eine Rechnung manuell auf Papier
reinkommt, scannst du sie und uploadest sie in OWE.

### 3. Richtigkeit (Rn. 41)

> Inhalte muessen den tatsaechlichen Verhaeltnissen entsprechen.

**OWE deckt das ab durch:**

- **OCR + Indexfeld-Extraktion** mit konfigurierbaren Extractoren
  (Heuristik, ZUGFeRD-XML, Regex, KI). Bei ZUGFeRD-Rechnungen sind
  die Felder garantiert konsistent mit dem PDF.
- **Manuelle Korrektur** der Indexfelder ist moeglich; Aenderungen
  landen im Audit-Log mit Original- und Neuwert.
- **4-Augen-Prinzip via Workflows**: ein Beleg geht erst nach
  Approval in die Buchhaltung.

### 4. Zeitgerechte Buchungen und Aufzeichnungen (Rn. 47)

> Belege sind zeitnah zu erfassen.

**OWE deckt das ab durch:**

- **Automatische Erfassung** ueber IMAP/Folder/Webhook — keine
  Latenz durch manuelle Schritte.
- **SLA-Fristen** pro Approval-Step (Karenzzeit + Eskalation), damit
  Belege nicht in den Inboxen liegen bleiben.
- **Reminder-Mails** und Eskalations-Regeln stellen sicher, dass
  niemand vergessen wird.

### 5. Ordnung (Rn. 51)

> Geschaeftsvorfaelle muessen geordnet abgelegt sein, schnell
> auffindbar.

**OWE deckt das ab durch:**

- **Dokument-Archive** (Typen) als oberste Ordnung.
- **Indexfelder pro Typ** fuer strukturierte Suche.
- **OCR-Volltext** ueber alle Dokumente (mit
  [MeiliSearch](app:help.show?topic=search-meilisearch) skaliert das
  auf 1M+).
- **Tags + Akten** als Quer-Ordnungs-Mechanismen.

### 6. Unveraenderbarkeit (Rn. 58)

> Einmal erfasste Daten duerfen nicht spaeter veraendert werden,
> ohne dass Aenderung + Original erkennbar sind.

**OWE deckt das ab durch:**

- **Dokument-Versionierung**: alte Versionen werden NIE
  ueberschrieben. Bei „Neue Version" entsteht ein neuer Eintrag in
  der Chain mit eigener SHA-256, alle aelteren bleiben abrufbar.
- **SHA-256 Hash-Verifikation** bei Schreiben (siehe Auditlog-Ein-
  trag `attachment.uploaded`).
- **Audit-Hash-Kette** (siehe oben — Manipulation faellt auf).
- **WORM-Storage optional**: bei [Object-Storage](app:help.show?topic=object-storage)
  mit S3-Object-Lock im Compliance-Mode koennen Dateien selbst von
  root nicht mehr veraendert / geloescht werden, bis die Retention-
  Frist abgelaufen ist.

> [!TIP]
> Wer die hoechste Stufe will: ATTACHMENTS_DISK=s3 mit Object-Lock
> Compliance-Mode + 10 Jahre Retention. Dann ist die Datei
> physikalisch unveraenderbar, nicht nur per Software.

## Aufbewahrungsfristen

OWE unterstuetzt die **automatische** Anwendung der gesetzlichen
Fristen via [Aufbewahrungsregeln](app:help.show?topic=retention):

- Pro Dokumenttyp eine Mindestlaufzeit (z. B. 10 Jahre Rechnungen)
  und optional eine Maximal-Frist mit Aktion nach Ablauf:
  - **Pruefung markieren** — Workflow zur Loeschungs-Genehmigung
    triggern.
  - **Archivieren (Soft-Delete)** — aus dem aktiven Bereich nehmen.
  - **Endgueltig loeschen** — DSGVO-konform.

Wird taeglich um 03:15 ausgewertet (Cron).

> [!WARNING]
> **Loeschen nach Ablauf** ist die kritischste Operation. Pro
> Doku-Typ ueberlegen ob du wirklich automatisch loeschen lassen
> willst oder lieber `mark_for_review` setzt. Empfehlung: erst
> nach mehreren Jahren Live-Betrieb auf `delete` umstellen.

## Verfahrensdokumentation: was du selbst schreiben musst

Die GoBD verlangt eine **Verfahrensdokumentation** — kein OWE-Feature
generiert dir die. Es ist ein Dokument deiner Organisation. Du
beschreibst darin:

1. **Welche Belegarten** in OWE archiviert werden (Rechnungen,
   Vertraege, Personalakten, ...).
2. **Wie die Erfassung** ablaeuft (Mail-Eingang, Scanner, manuell).
3. **Welche Workflows** fuer welche Belegart laufen — am besten als
   Diagramm + Beschreibung der Knoten.
4. **Wer zustaendig** ist (Verantwortlichkeiten, Vertretungsregeln).
5. **Aufbewahrungsfristen** pro Belegart inkl. Geschaeftsgrundlage
   (z. B. § 147 AO).
6. **Technische Sicherung**: Backup-Konzept, Restore-Tests, SHA-
   Verifikation, Disaster-Recovery.
7. **Aenderungs-Historie** der Verfahrensdokumentation selbst —
   wann hat sich etwas geaendert, von wem genehmigt.

### Vorlage / Gerüst

Das nachfolgende Geruest deckt die Mindest-Anforderungen ab. Kopier
es, fuell es aus, halte es aktuell:

```
1. Allgemeine Angaben
   1.1 Bezeichnung des Verfahrens: Dokumenten-Management + Workflow mit OWE
   1.2 Verantwortlicher: <Name, Position>
   1.3 Stand: <Datum>, Version <n.n>
   1.4 Geltungsbereich: <Welche Organisations-Teile>

2. Beschreibung der eingesetzten Software
   2.1 Produkt: Open Workflow Engine (OWE), Version <git-rev>
   2.2 Hosting: <on-premise / Cloud / Hoster>
   2.3 Backup-Strategie: <was wird gesichert, wo, wie oft>
   2.4 Wartungsfenster: <wann, was wird upgedatet>

3. Daten-Erfassung
   3.1 Eingangs-Kanaele:
       - IMAP-Postfach <name>@<domain> -> Workflow <name>
       - Folder-Inbox /scans/eingang -> Workflow <name>
       - Manueller Upload via Dokumenten-Liste
   3.2 Klassifizierung: <Heuristik / KI / manuell>
   3.3 Indexierung: pro Doku-Typ Schema in OWE definiert,
       Aenderung nur durch <Rolle>

4. Workflows
   4.1 Liste der aktiven Workflows mit Zweck:
       - Rechnungseingang: pro Lieferanten-Rechnung Genehmigung +
         Buchung in DATEV.
       - <weitere>
   4.2 Pro Workflow eine eigene Diagramm-Anlage.

5. Aufbewahrung
   5.1 Pro Belegart:
       - Rechnung: 10 Jahre (§ 147 AO Abs. 3), Aktion nach Ablauf:
         mark_for_review.
       - <weitere>
   5.2 Loeschungs-Verfahren: <wie wird der Review-Prozess gehandhabt>

6. Berechtigungen
   6.1 Rollen-Liste mit Aufgabenbeschreibung
   6.2 Pro Rolle: sichtbare Dokument-Archive + Workflow-Permissions
   6.3 Aenderung von Berechtigungen nur durch <Admin-Rolle>, mit
       Audit-Log-Eintrag.

7. Sicherung der Unveraenderbarkeit
   7.1 Versionierung: alte Versionen werden physikalisch behalten
   7.2 SHA-256-Hash pro Datei (Verifikation per Cron oder manuell)
   7.3 Audit-Hash-Kette (Manipulation faellt auf)
   7.4 WORM-Storage: <ja / nein, wenn ja: wo + Retention>

8. Datenzugriff Pruefer
   8.1 OWE bietet CSV-Export pro Suche (immediate Z3 read-only)
   8.2 Audit-Log ist als CSV/JSON ueber das Admin-UI abrufbar
   8.3 Backup-Archiv kann auf Wunsch zur Verfuegung gestellt werden

9. Aenderungs-Historie der Verfahrensdokumentation
   <Datum> <Initialen> <Aenderung>
```

## Zertifizierung

OWE selbst ist **nicht zertifiziert** — eine Software-Zertifizierung
gibt's nach GoBD ohnehin nicht (siehe Rn. 184: das BMF zertifiziert
keine DMS-Produkte). Was es gibt: **Pruefberichte von
Wirtschaftspruefern** ueber den Einsatz in deinem Betrieb. Wenn du
einen brauchst:

- Wirtschaftspruefer kontaktieren, der GoBD-Audits macht.
- Verfahrensdokumentation vorlegen (siehe oben).
- OWE-Audit-Log + Workflow-Definitionen + Backup-Konzept dazu.

Der WP attestiert dann „die in der Verfahrensdokumentation
beschriebenen Prozesse und die eingesetzten Werkzeuge sind nach
unserer Pruefung geeignet, die GoBD-Anforderungen zu erfuellen".

Geschaetzte Kosten: 2-5k EUR fuer KMU einmalig + ca. 1k jaehrliches
Update-Review.

## Praktische Tipps

- **Audit-Log nicht abschalten**. Das Modul ist Pflicht.
- **Backup-Restore mind. jaehrlich testen** — sonst hast du keinen
  Backup, sondern ein Backup-Versprechen.
- **Retention-Regeln pruefen** wenn sich Gesetze aendern (z. B.
  beim Auslaufen 10-Jahres-Frist auf neue Belegarten).
- **Audit-Log fuer Pruefer exportieren**: per CSV-Export oder per
  direkten DB-Snapshot. Pruefer wollen i. d. R. einen lesbaren
  Auszug.

## Beispiel-Audit-Trail eines Belegs

So sieht der vollstaendige Lebenslauf einer Eingangsrechnung in
OWE aus (Audit-Eintraege, chronologisch):

```
mailbox.fetched           Mail eingegangen mit Anhang rechnung.pdf
attachment.uploaded       SHA-256: a3f2... abgelegt in attachments/2026/04/...
attachment.indexed        Felder erkannt: rechnungsnummer=4711, betrag=1234.50
workflow.instance.started Workflow 'Rechnungseingang' #1234 gestartet
workflow.task.assigned    Aufgabe an buchhaltung@firma.de
pdf.annotation.added      Stempel 'Geprueft' (Seite 1)
attachment.indexed_fields.approval  bemerkung='Sachlich richtig'
workflow.task.completed   Genehmigt von buchhaltung@firma.de
workflow.task.assigned    Aufgabe an leitung@firma.de (Eskalation)
workflow.task.completed   Genehmigt von leitung@firma.de
workflow.instance.completed  ok
datev.exported            Im Monatsabschluss April mitgeschickt
```

Jeder dieser Eintraege ist:

- **hash-verkettet** (kein nachtraegliches Einfuegen / Loeschen)
- **mit Zeitstempel** (Serverzeit ist NTP-synchronisiert)
- **mit User-ID** wer die Aktion ausgeloest hat
- **mit Datenwerten** (old/new) wo sinnvoll

Das ist der Audit-Trail den ein Pruefer sehen will. Du kannst ihn
ueber [Audit-Log](app:admin.audit.index) filtern und als CSV exportieren.
