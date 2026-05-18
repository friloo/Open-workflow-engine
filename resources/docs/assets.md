# Assets — Fuehrerscheine, Unterweisungen, Zertifikate

Ein Asset ist ein nutzergebundenes Dokument mit Ablaufdatum.

## Felder

- **Inhaber**: der Benutzer im System, dem das Asset gehoert
- **Typ**: z. B. `fuehrerschein`, `g25-untersuchung`
- **Gueltig bis**: Datum, ab dem der Workflow ausgeloest wird
- **Vorlauffrist (Tage)**: Anzahl Tage vor `valid_until`, ab wann der Workflow starten soll
- **Pruef-Workflow**: optional, aktiver Workflow mit Trigger *Wiederkehrend*
- **Dateien**: PDFs, Bilder oder Office-Dokumente (max. 15 MB) als Scan

## Wiedervorlage

`asset:check-due` laeuft taeglich um 06:00 Uhr (siehe `routes/console.php`).
Sobald `valid_until - lead_time_days <= heute`, startet der hinterlegte
Workflow mit dem Inhaber als Initiator und Subject-User.

## Dateien (z. B. Fuehrerschein-Scan)

Im Asset koennen mehrere Dateien hochgeladen werden. Sobald der Workflow
ausgeloest wird, werden die Anhaenge **kopiert** und an die Workflow-Instanz
gehaengt. Der Genehmiger sieht den Scan direkt in seiner Aufgabe und kann
ihn mit dem vorgezeigten Dokument vergleichen.

## E-Mail an den Inhaber

Setze im Notify-Knoten den Empfaenger-Typ **Asset-Inhaber / Subject-User**.
Der Inhaber bekommt eine Mail, mit `{{ subject_user_name }}` und
`{{ asset_name }}` als Platzhalter im Body.

## CSV-Import

Spalten: `user_email`, `name`, `type`, `valid_until`, `lead_time_days`, `notes`.

```
user_email;name;type;valid_until;lead_time_days
maria@firma.de;Fuehrerschein Klasse B;fuehrerschein;2026-04-12;30
```
