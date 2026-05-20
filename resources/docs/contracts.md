# Vertragsmanagement

Verwalte Wartungs-, Miet-, Versicherungs- und Lizenzvertraege an einem
zentralen Ort — mit automatischer Wiedervorlage vor Ablauf der
Kuendigungsfrist.

> Zugriff: **[Stammdaten → Vertraege](app:contracts.index)** (Rechte
> `contracts.view` zum Ansehen, `contracts.manage` zum Anlegen / Bearbeiten)

## Wozu

Klassisches KMU-Problem: Vertrag laeuft schweigend weiter, weil
niemand drangedacht hat zu kuendigen. Hier gibt's eine
Dauer-Wiedervorlage:

1. Anlegen: Beginn, Ende, Kuendigungsfrist (Tage vor Ende)
2. Verantwortlichen festlegen
3. OWE prueft taeglich (Cron `contracts:check-deadlines`)
4. Sobald die Frist erreicht ist (z. B. 90 Tage vor Ende), bekommt
   der Verantwortliche eine **In-App-Benachrichtigung** (Glocke
   oben rechts)
5. Im Vertrag selbst aendert sich der Status auf
   „Kuendigungsfrist erreicht" — orange Badge in der Liste

## Vertragsarten

Vertraege werden in **Vertragsarten** organisiert (z. B. Mietvertrag,
Wartung, Software-Lizenz). Verwaltung unter
**[Vertraege → Arten](app:contract-types.index)**.

Pro Vertragsart wird festgelegt:

- **Name** + Farb-Markierung
- **Default-Kuendigungsfrist** — wird beim Neuanlegen eines Vertrages
  dieses Typs automatisch vorbelegt
- **Rollen-Berechtigungen**: welche Rollen Vertraege dieser Art
  - **sehen** (lesen)
  - oder zusaetzlich **bearbeiten/loeschen** duerfen (`+M`)

Vorteil: einmal in der Vertragsart konfiguriert, gilt das fuer alle
zukuenftigen Vertraege dieses Typs.

## Berechtigungen pro Vertrag

Zusaetzlich zur Vertragsart-Berechtigung kannst du pro **einzelnem
Vertrag** weitere Rollen freischalten (z. B. „nur diesen einen
Vertrag darf auch die Buchhaltung sehen"). Im Bearbeitungs-Form
unter „Zusaetzliche Berechtigungen pro Rolle".

### Sichtbarkeit zusammengefasst

Ein User sieht einen Vertrag, wenn EINES davon zutrifft:

1. Er ist **Admin** (sieht alles)
2. Er ist der **Verantwortliche** (owner)
3. Eine seiner **Rollen** ist fuer die **Vertragsart** des Vertrages freigeschaltet
4. Eine seiner **Rollen** ist **direkt fuer diesen Vertrag** freigeschaltet
5. Der Vertrag hat **keine Vertragsart** (Legacy / unklassifiziert) — analog
   zu Dokumenten ohne Typ

Bearbeiten/Loeschen geht zusaetzlich nur mit der globalen
`contracts.manage`-Permission UND einer der oben genannten
Manage-Berechtigungen (`+M`).

## Dokumente am Vertrag (PDFs, Anlagen)

Auf der Detail-Seite eines Vertrages gibt's eine Karte
**„Dokumente"**: dort kannst du die Vertrags-PDF, Anlagen, AGB,
Schriftverkehr und beliebige weitere Dateien hochladen. Erlaubte
Formate: PDF, DOCX, XLSX, PNG, JPG. Max. 15 MB pro Datei.

Hochgeladene Dateien werden:

- **Revisionssicher gespeichert** mit SHA-256-Hash (Aenderung
  unmoeglich, Soft-Delete erhalten die Datei in der Historie)
- **OCR-indexiert**, falls Volltext-Suche aktiv ist — die
  globale Schnellsuche findet dann Inhalte aus Vertraegen
- **Im Audit-Log** mit Hash + Hoch-Lader vermerkt
  (`attachment.uploaded`)

Wer darf was?

- **Sehen + Download**: jeder, der den Vertrag selbst sehen darf
  (siehe Berechtigungen oben)
- **Hochladen + Loeschen**: nur wer den Vertrag bearbeiten darf
  (Admin, Owner, Rolle mit `+M` am Typ oder am Vertrag)

## Felder

| Feld | Bedeutung |
|------|-----------|
| Name | Wie der Vertrag im Tool heisst |
| Vertragspartner | Wer auf der anderen Seite steht |
| Kategorie | Wartung / Miete / Versicherung / Software-Lizenz / Beratung … |
| Beschreibung | Freitext fuer Notizen |
| Beginn | Vertragsbeginn |
| Ende | Vertragsende (Pflicht fuer Frist-Berechnung) |
| Kuendigungsfrist | Tage VOR Ende, ab denen erinnert wird |
| Auto-Verlaengerung | Optional: nach Ende um X Monate |
| Verantwortlich | User, der die Erinnerung bekommt |

## Status

- **Aktiv** (gruen) — Vertrag laeuft, Frist noch nicht erreicht
- **Kuendigungsfrist erreicht** (orange) — jetzt entscheiden:
  kuendigen oder weiterlaufen lassen
- **Abgelaufen** (rot) — Endedatum erreicht

Status wird taeglich um 06:30 automatisch aktualisiert. Nicht manuell
setzbar — wird aus end_date + notice_period_days berechnet.

## Cron-Verhalten

Pro Vertrag wird **maximal alle 90 Tage** eine Erinnerung verschickt
(damit der Verantwortliche nicht jeden Tag genervt wird). Falls
mehrere Reminder noetig sind: das Datum des letzten steht im Vertrag
unter „Letzte Erinnerung".

## Integration

- **Audit-Log**: Anlegen / Aendern / Loeschen wird festgehalten
  (`contract.created` / `contract.updated` / `contract.deleted`)
- **Notifications**: `contract.notice_due` mit Direkt-Link zum Vertrag
- **Filter in Liste**: Alle / Aktiv / Frist erreicht / Abgelaufen,
  plus Volltext-Suche ueber Name + Partner + Kategorie

## Tipp fuer Auto-Verlaengerung

Wenn der Vertrag sich automatisch um z. B. 12 Monate verlaengert,
solltest du nach der ersten Verlaengerung das `end_date` manuell
hochsetzen (OWE rechnet das nicht selbst hoch — das waere zu
aggressiv ohne explizite Bestaetigung).
