# Microsoft Teams-Integration

OWE kann Genehmigungs-Aufgaben zusaetzlich zur E-Mail in einen
Microsoft-Teams-Channel posten. Das Team sieht den Antrag direkt
im Chat-Tool — Klick auf den Button bringt sie zu OWE.

> [!IMPORTANT]
> Aktuell **read-only**: das Posting in Teams ist eine Benachrichtigung
> mit Deep-Link. Approven direkt aus Teams (per Click auf den Button)
> ist noch nicht moeglich — das braeuchte eine Microsoft Graph-OAuth-
> App. Geplant. Vorerst: Teams als zusaetzlicher Notification-Kanal.

## Einrichten

### 1. Channel-Webhook in Teams anlegen

In Microsoft Teams:

1. Den gewuenschten Channel oeffnen.
2. Klick auf die drei Punkte **•••** rechts neben dem Channel-Namen.
3. **Workflows** oder **Verbindungen** → **Eingehender Webhook**
   hinzufuegen.
4. Namen vergeben (z. B. „OWE-Approvals") und optional Logo.
5. **Erstellen** → eine HTTPS-URL wird generiert. Diese kopieren.

> [!NOTE]
> Neuere Tenant-Konfigurationen blenden die alten 'Office 365 Connectors'
> aus. Statt dessen ueber die **'Workflows app'** den Webhook bauen —
> dieselbe URL-Logik, OWE merkt keinen Unterschied.

### 2. URL in OWE eintragen

[Admin → Einstellungen → Integrationen](app:admin.settings.integrations):
URL ins Feld **Teams Channel Webhook-URL** und speichern.

Klick auf **Test-Nachricht senden** — eine kurze Test-Card sollte im
Teams-Channel auftauchen.

### 3. Fertig

Ab sofort: bei jedem **Approval-Step**, der startet, schickt OWE eine
Adaptive-Card mit:

- Titel: „<Approval-Label> · <Workflow-Name>"
- Antragsteller-Name
- Wichtigste Antrags-Daten (Betrag, Kostenstelle, Rechnungsnummer, ...)
- Button **In OWE oeffnen** → bringt den User auf die Aufgaben-Seite

## Pro-Workflow-Konfiguration

Wer pro Workflow einen anderen Channel will (z. B. Buchhaltung-Channel
fuer Rechnungen, Personal-Channel fuer Urlaubsantraege), kann pro
Approval-Knoten eine eigene Webhook-URL setzen.

Aktuell noch nicht UI-konfigurierbar — das Feld heisst
`teams_webhook_url` im Knoten-Daten-JSON. Manuell ueber die
Workflow-Versions-API oder im Definition-Editor setzen.

Plus `notify_teams: false` deaktiviert das Teams-Posting fuer diesen
Knoten (z. B. bei sehr internen Approvals).

## Was sieht der User in Teams?

```
┌──────────────────────────────────────────┐
│ Genehmigung · Rechnungseingang           │
├──────────────────────────────────────────┤
│ Antragsteller: max.mustermann@example.de  │
│                                          │
│  Betrag       1.234,50                    │
│  Kostenstelle K-2026-IT                   │
│  Rechnungsnr. 4711-2026                   │
│  Eingegangen  19.05.2026 14:32            │
│                                          │
│  [ In OWE oeffnen ]                       │
└──────────────────────────────────────────┘
```

Die Card-Farbe ist indigo (themeColor 6366f1).

## Troubleshooting

- **Test-Nachricht kommt nicht an**: pruef die URL — sie muss mit
  `outlook.office.com/webhook/` oder einer ähnlichen Teams-Domain
  beginnen. Bei `403`/`404`-Antwort wurde der Channel-Webhook
  vermutlich geloescht.
- **Card sieht zerschossen aus**: alte Teams-Clients koennen mit
  Adaptive-Cards Probleme haben. OWE schickt deshalb das aeltere
  `MessageCard`-Format, das ueberall unterstuetzt wird.
- **Zu viel Spam**: Setting auf leer setzen ODER pro Workflow
  `notify_teams: false` setzen.

## Was kommt noch?

- **Approve direkt aus Teams** — braucht eine Microsoft Graph
  registrierte App + OAuth-Flow. Geplant fuer den naechsten
  Integrations-Cycle.
- **Slack-Variante** — analoge Implementierung mit Incoming-Webhook
  + Block-Kit. Aktuell noch nicht gebaut, wenn gewuenscht melden.
- **Mehrere Channels pro Workflow** (z. B. fuer Eskalation Spaeter
  einen anderen Channel) — siehe `escalation_*`-Felder im Approval-
  Knoten.
