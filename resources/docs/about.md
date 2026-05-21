# Ueber dieses Tool & Disclaimer

## Autor

**Open Workflow Engine** wurde entwickelt von

**[Friederich Loheide](https://loheide.eu)** · `loheide.eu`

Fragen, Lob, Bug-Reports und Mitarbeit sind willkommen — am besten
ueber das oeffentliche Repository als Issue.

## Was ist OWE?

Ein self-hosted Tool, das **Dokumentenmanagement (DMS)**,
**Workflow-Engine** und **Vertragsmanagement** in einer Anwendung
zusammenbringt — gedacht fuer kleine und mittelstaendische
Unternehmen, Vereine und oeffentliche Einrichtungen, die ihre Daten
nicht in der Cloud sehen wollen.

- Workflows mit Drag-Drop-Designer, Versionen, Quorum, Eskalation
- DMS mit OCR, Versionierung, ZUGFeRD/XRechnung
- Vertragsmanagement mit Frist-Reminder + ACL
- E-Akten / Aktendeckel
- Reports & KPIs
- REST-API mit Token-Auth, OpenAPI/Swagger-Doku
- SSO (M365, OIDC, Google, SAML, LDAP/AD)
- DSGVO + GoBD-Bausteine
- PWA + iCal-Feed + Web-Push fuer mobile Nutzung

## Wie wurde es entwickelt?

Dieses Tool wurde **unter anderem mit KI-Code-Generierung erstellt**.
Der Code-Bestand stammt aus einer iterativen Mensch+KI-Zusammenarbeit:
Architektur, Anforderungen und Feature-Auswahl wurden von Friederich
Loheide festgelegt, ein wesentlicher Teil der Code-Implementierung
und Tests wurde KI-unterstuetzt erzeugt und manuell ueberprueft.

## ⚠️ Wichtig: Haftungsausschluss

**Open Workflow Engine wird ohne jegliche Gewaehrleistung
bereitgestellt — Nutzung auf eigene Gefahr.**

Insbesondere gibt es **keine Garantie** fuer:

- **Fehlerfreiheit** — trotz Test-Suite (aktuell 324 Tests) koennen
  Bugs in nicht-getesteten Code-Pfaden, Edge-Cases oder
  Integrations-Layern stecken.
- **Datensicherheit** — die Hash-Kette ist gewuenscht, aber
  Verschluesselung auf Disk, Backup-Konzept und Zugriffs-Audit
  liegen in deiner Hand.
- **DSGVO-Konformitaet** — das Tool stellt Bausteine bereit
  (Auskunft, Vergessenwerden, Audit-Log), aber das **Verfahren** und
  die **Dokumentation** sind deine Verantwortung als
  Verantwortlicher i. S. d. DSGVO.
- **GoBD-Tauglichkeit** — die technische Grundlage ist gelegt
  (immutable Versionen, Hash-Kette, signierte PDFs), aber die
  Zertifizierung erfolgt nicht durch dieses Tool. Sprich mit deinem
  Wirtschaftspruefer.
- **Folgeschaeden** — Datenverlust, entgangener Gewinn, Bussgelder
  oder Reputationsschaeden, die aus dem Einsatz dieser Software
  entstehen, gehen zu deinen Lasten.
- **KI-Generierungs-Artefakte** — auch wenn der Code reviewt und
  getestet wurde: KI kann subtile Bugs, falsche Sicherheits-Annahmen
  oder anti-pattern erzeugen, die in der oberflaechlichen Pruefung
  nicht auffallen.

## Was du tun solltest

- **Regelmaessige Backups** (Tool-built-in Backup-Module nutzen,
  ZUSAETZLICH extern wegsichern)
- **Updates** zeitnah einspielen, Changelog lesen
- **Audit-Trail** stichprobenartig pruefen
  (`/admin/audit/verify`)
- **Berechtigungen** dokumentieren — Rolle vs. Person
- **Penetrationstest / externes Audit** bevor du sensible
  Daten reintust
- **Versionierungs- und Verfahrensdokumentation** fuehren, falls
  du in einem regulierten Umfeld bist

## Lizenz

MIT-Lizenz — kostenlos nutzen, anpassen, weiterverkaufen.
Der Copyright-Hinweis muss erhalten bleiben. MIT-Lizenz heisst nicht
„garantiert sicher" — sondern „nutze auf eigenes Risiko".

## Beitragen

Pull Requests willkommen. Bug-Reports gerne mit
Reproduktions-Schritten + Logs. Feature-Wuensche als Issue.

> Wenn dir das Tool Zeit oder Cloud-Kosten spart, freuen wir uns
> ueber einen Stern ⭐ im Repository — und ueber Feedback noch mehr.
