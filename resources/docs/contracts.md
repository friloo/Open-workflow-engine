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
