<div align="center">

```
   ██████╗ ██╗    ██╗███████╗
  ██╔═══██╗██║    ██║██╔════╝
  ██║   ██║██║ █╗ ██║█████╗
  ██║   ██║██║███╗██║██╔══╝
  ╚██████╔╝╚███╔███╔╝███████╗
   ╚═════╝  ╚══╝╚══╝ ╚══════╝
       Open Workflow Engine
```

### Self-hosted Dokumenten-Management mit Workflow-Engine. Built fuer KMU.

**Ein DMS, das deine Dokumente nicht nur ablegt — sondern sie liest, klassifiziert, weiterleitet und genehmigen laesst.**
Eingehende Rechnung per Mail → Kostenstelle automatisch erkannt → richtige Person genehmigt. Ohne Cloud. Ohne SSH. Ohne Composer.

[![PHP](https://img.shields.io/badge/PHP-8.2%2B-777BB4?logo=php&logoColor=white&style=flat-square)]()
[![Laravel](https://img.shields.io/badge/Laravel-11-FF2D20?logo=laravel&logoColor=white&style=flat-square)]()
[![Tests](https://img.shields.io/badge/Tests-238%20passing-10B981?style=flat-square)]()
[![Self-hosted](https://img.shields.io/badge/Self--hosted-✓-0EA5E9?style=flat-square)]()
[![DSGVO](https://img.shields.io/badge/DSGVO-by%20design-7C3AED?style=flat-square)]()
[![Revisionssicher](https://img.shields.io/badge/Revisionssicher-SHA--256-F59E0B?style=flat-square)]()
[![License](https://img.shields.io/badge/License-MIT-475569?style=flat-square)]()

[**Was kann es?**](#-was-owe-kann) · [**Use-Cases**](#-was-kannst-du-damit-konkret-bauen) · [**Installation**](#-installation-nur-eine-datei-hochladen) · [**Live-Beispiel**](#-live-beispiel-rechnungseingang) · [**Vergleich**](#%EF%B8%8F-owe-vs-saas-vs-selbst-zusammenstecken) · [**Architektur**](#%EF%B8%8F-architektur) · [**Roadmap**](#%EF%B8%8F-roadmap)

</div>

---

## 🎯 In drei Saetzen

OWE ist im Kern ein **Dokumenten-Management-System** — mit Versionierung, OCR-Volltextsuche, automatischer Feld-Erkennung (Rechnungsnummer, Kostenstelle, IBAN, Datum, Betrag), revisionssicherer Hash-Kette, DSGVO-Aufbewahrungsregeln und Sharing-Links. Auf dem **Workflow-Modul** obendrauf werden Dokumente intelligent geroutet: Mail-Eingang → automatische Klassifizierung → Genehmigung beim richtigen Empfaenger. Alles laeuft auf **jedem PHP-Hosting** (auch dem 5-EUR-Tarif), ist **komplett ueber den Browser bedienbar** — von der Erstinstallation bis zum Update — und du behaeltst deine Daten, deine Kontrolle und deine DSGVO-Hoheit.

---

## 💡 Warum OWE?

<table>
<tr>
<td width="33%" valign="top" align="center">

### 🏢 Cloud-Tools werden teuer
Zapier, n8n cloud, DocuSign, Jira — die Rechnungen schwellen jedes Jahr.
<br><br>
**Mit OWE zahlst du dein Hosting. Punkt.**

</td>
<td width="33%" valign="top" align="center">

### 🇪🇺 Deine Daten gehoeren dir
Rechnungen, Mitarbeiter-Daten, Workflows — alles auf deinem Server, in deiner DB, unter deinem DSGVO-Verfahrensverzeichnis.
<br><br>
**Kein US-Cloud-Drama.**

</td>
<td width="33%" valign="top" align="center">

### 🛠️ Kein DevOps noetig
Kein Docker, Kein Kubernetes, kein Redis, keine Queue-Worker. **Ein Cron-Eintrag, SQLite oder MySQL — fertig.**
<br><br>
Du bedienst es ueber den Browser, nicht ueber ein Terminal.

</td>
</tr>
</table>

---

## 👥 Fuer wen ist OWE?

- 📁 **Jeden, der bisher PDFs in Ordnerstrukturen schiebt** und sich ein revisionssicheres DMS mit Volltext + Feld-Erkennung wuenscht — aber dafuer nicht in die Cloud will.
- 📊 **Buchhaltung & Controlling**, die Rechnungen automatisch der richtigen Kostenstelle zuordnen wollen — ohne 200 EUR/Monat fuer ein Cloud-DMS.
- 🏛️ **Vereine und Stiftungen**, die ihren Vorstands-Genehmigungs-Workflow digital abbilden wollen — mit Vier-Augen-Prinzip und Audit-Log.
- 🔧 **IT-Verantwortliche in KMU**, die Self-hosted brauchen, aber keine Lust auf eine 3-Wochen-Kubernetes-Einrichtung haben.
- 🏥 **Praxen, Kanzleien, Handwerksbetriebe** mit DSGVO-Compliance-Pflicht und kleinem Budget.
- 🎓 **Schulen, Hochschulen, Behoerden**, die Workflows fuer interne Genehmigungen brauchen — Antraege, Reisekosten, Materialbestellungen.

> *Wenn du Drag-and-Drop-Workflow-Bauen in einem Browser-Tab oeffnen, eine SQLite-Datei sehen und alle Mitarbeiter-Daten in **deiner** Hand wissen willst — bist du hier richtig.*

---

## 🎨 Was kannst du damit konkret bauen?

OWE ist **generisch** — Dokumenttypen, Felder, Workflows und Empfaenger-Regeln
sind alles deine Konfiguration. Du bist nicht auf „Rechnungen" festgelegt.
Hier eine Galerie konkreter Anwendungsfaelle, die heute schon Out-of-the-Box
bauen lassen:

<table>
<tr>
<td width="33%" valign="top">

### 💶 Finanzen & Buchhaltung
- 📥 **Rechnungseingang** mit Auto-Routing zur Kostenstelle *(Vorlage da)*
- ✅ **Bestellfreigabe** mit Stufen je Betrag *(Vorlage da)*
- 📋 **Auftragsbestaetigungs-Freigabe**
- 💸 **Spesen-/Reisekosten-Abrechnung**
- 🏦 **Lieferantenanlage** mit IBAN-Pruefung
- 📑 **Vertrags-Freigaben** (Lieferant, Dienstleister, Miete)
- 🔍 **Mahnwesen** (Status-Workflow mit Eskalation)

</td>
<td width="33%" valign="top">

### 👥 HR & Personal
- 🏖️ **Urlaubsantrag** *(Vorlage da)*
- 🤒 **Krankmeldung-Eingang per Mail** (IMAP → Personalakte)
- 🪪 **Fuehrerschein-Sichtkontrolle** (wiederkehrend, Asset-Workflow)
- 🦺 **Sicherheitsunterweisungen** (G-25, Erste-Hilfe-Schein, ...)
- 📝 **Onboarding-Checkliste** mit mehreren Genehmigungen
- 🎓 **Weiterbildungsantraege**
- 🏢 **Homeoffice-Antrag**
- 📃 **Zeugnis-/Bescheinigungs-Anforderung**

</td>
<td width="33%" valign="top">

### 🛡️ Recht & Compliance
- 📜 **DSGVO-Auskunftsersuchen** (Art. 15)
- 🗑️ **DSGVO-Loeschantrag** mit Begruendung
- 📅 **Vertragsverlaengerung** rechtzeitig pruefen (wiederkehrend)
- 🔐 **NDA-Freigabe**
- 📒 **Sitzungsprotokoll-Freigabe** (z. B. Vorstand)
- 🔍 **Aufbewahrungs-Ablauf** mit Pruefungs-Aktion *(eingebaut)*
- 🕵️ **Vier-/Sechs-Augen-Genehmigungen** *(Quorum eingebaut)*

</td>
</tr>
<tr>
<td valign="top">

### 🛒 Vertrieb & Marketing
- 💼 **Angebotsfreigabe** (Stufen nach Volumen)
- 🤝 **Kundenanlage / Onboarding**
- 📰 **Pressemitteilung-Freigabe**
- 📣 **Social-Media-Post-Freigabe**
- 📧 **Newsletter-Freigabe**
- 🎨 **Werbematerial-Freigabe** mit PDF-Beleg
- 📊 **Wettbewerbs-Analyse-Eingang** per Mail

</td>
<td valign="top">

### 🛠️ IT & Operations
- 🔧 **IT-Bestellantrag** (Hardware, Software)
- 🔑 **Zugangs-/Berechtigungs-Antrag**
- 🪪 **Software-Lizenz-Anfrage**
- 🚨 **Stoerungsmeldung** mit Eskalation
- 🛠️ **Wartungsfreigabe** (z. B. Anlagen)
- 📦 **Werkzeug-/Geraete-Ausleihe**
- 🏗️ **Bauvorhaben-/Anschaffungs-Antrag**
- 📋 **Stammdaten-Aenderungen** mit Vier-Augen

</td>
<td valign="top">

### 🏛️ Vereine & Bildung
- 🆕 **Mitgliedsantrag** (online ueber oeffentliches Formular)
- 🚪 **Mitgliedsaustritt**
- 💶 **Beitragsaenderung**
- 🧾 **Spendenbescheinigung-Anforderung**
- 🗳️ **Vorstands-Genehmigung** (Anschaffung, Spenden)
- 📚 **Materialbestellung** (Schule, Verein)
- 🎓 **Praktikumsbescheinigung**
- 🚌 **Klassenfahrt-Genehmigung** (oeffentliches Formular)

</td>
</tr>
<tr>
<td valign="top">

### 🏥 Praxis & Kanzlei
- 📄 **Befund-Eingang** per Mail (IMAP → Patientenakte)
- 💊 **Rezept-Anforderung**
- 📋 **Ueberweisungs-Vorlage**
- ✍️ **Mandats-/Vollmachts-Erteilung**
- 📅 **Termin-Anfrage** (oeffentliches Formular)
- 🔒 **DSGVO-Auftragsverarbeiter-Vertraege**

</td>
<td valign="top">

### 🏗️ Generische Genehmigungen
- 🪙 **„Brauchen wir was?"** — beliebige Antraege
- 📝 **Antrag mit Begruendung** + Pflichtkommentar bei Ablehnung
- 🌳 **Mehrstufige Eskalations-Ketten** (Team-Lead → Bereich → GF)
- ⏱️ **Erinnerungs-Mails** bei laenger offenen Tasks
- 📨 **Mail-Approval** ohne Login fuer Vorgesetzte unterwegs

</td>
<td valign="top">

### 🤖 Integrationen mit Drittsystemen
- 🎫 **Ticket-Erstellung** in Jira, GitLab, Zammad ueber HTTP-Knoten
- 💬 **Slack/Teams/Mattermost-Benachrichtigung** ueber Webhook
- 🛒 **Bestellung aus Shop** (Shopify, WooCommerce) per Incoming Webhook
- 📥 **Daten-Bezug** vom ERP (HTTP + Response-Mapping)
- 🔄 **Bidirektionaler Sync** mit n8n/Make ueber API-Tokens
- 📊 **Status-Push** an externes Dashboard

</td>
</tr>
</table>

> 💡 **Alle aufgelisteten Faelle nutzen dieselben Bausteine**:
> Dokumenttyp + Schema + Lookup-Liste + Workflow-Designer. Du baust den
> Workflow grafisch, definierst deine Felder, pflegst deine Listen — fertig.
> Keine Programmierung noetig.

---

## ✨ Was OWE kann

<table>
<tr>
<td width="50%" valign="top">

### 📄 Dokumenten-Management (Kern)
- 📥 **Bulk-Upload** · Drag-and-Drop · Inline-Vorschau (PDF/Bild)
- 🔢 **Versionierung** pro Dokument (Chain-Konzept, alle Versionen revisionssicher)
- 🔍 **OCR-Volltextsuche** (pdftotext + tesseract automatisch)
- 🧠 **Frei definierbare Felder pro Dokumenttyp** — beliebige Schluessel (`rechnungsnummer`, `vertragsbeginn`, `mitgliedsnummer`, `kfz_kennzeichen`, ...) mit Typ (string · date · currency · iban · email · number)
- 🎯 Erkennung: **Heuristik** (eingebaut: Rechnungsnr, Datum, EUR-Betrag, IBAN, USt-IdNr, E-Mail) · **eigener Regex** · **Lookup-Liste (anlernen ueber deine Listen!)** · KI optional
- 📨 **Postkorb** fuer eingehende Mails / unzugeordnete Dokumente
- 🔎 Filter auf erkannte Felder + **CSV-Export** fuer Buchhaltung
- 🔗 **Sharing-Links** mit Passwort, Ablauf-Cap, Auto-Review-Mails
- 🗑️ **Aufbewahrungsregeln** pro Dokumenttyp (DSGVO-Aktion: archivieren / loeschen / pruefen)
- 🔐 **SHA-256-Hashing** aller Dateien — Manipulationen fallen sofort auf
- 🏷️ **Dokumenttypen** mit rollenbasierter Sichtbarkeit (Buchhaltung sieht Rechnungen, HR sieht Fuehrerscheine)

</td>
<td width="50%" valign="top">

### 🔄 Workflow-Engine (rund ums DMS)
- ✏️ **Drag-and-Drop-Designer** (Drawflow) mit Versionierung
- 7 Knotentypen: Start · Bedingung · Genehmigung · HTTP · PDF · Mail · Ende
- Empfaenger: User · Rolle · Vorgesetzter · **Lookup-Liste**
- **Parallel-Quorum**: alle muessen zustimmen / n-aus-m
- Trigger: Formular · Manuell · Zeitplan · **IMAP-Mail** · Webhook · API
- 🏖️ **Vertretungsregelung** automatisch bei Urlaub
- 🧪 **Trockenlauf** vor Aktivierung mit Testdaten
- 📦 **3 Cookbook-Vorlagen** zum Import: Rechnung · Urlaub · Bestellung
- 🤖 **KI-Designer-Assistent** (OpenAI/DeepSeek/Ollama) — optional
- 📊 **Reports**: Durchlaufzeiten · SLA-Verletzungen · Bottleneck-Knoten · Top-Empfaenger
- 🗳️ **Genehmigung per Mail** ueber signierten Link (kein Login noetig)
- 🖋️ **Auto-PDF-Stempel** ("Genehmigt von X am Y") direkt in die Vertragsdatei
- 📑 **Vertragsmanagement** mit Vertragsarten, granularer ACL, Frist-Reminder, PDF-Anhaengen
- 🗂️ **E-Akten** buendeln Dokumente + Vorgaenge + Vertraege + Notizen
- 📱 **PWA + Web-Push** — Genehmigen unterwegs
- 📅 **iCal-Feed** pro User — Aufgaben + Vertrags-Fristen im Outlook/Apple Calendar

</td>
</tr>
<tr>
<td valign="top">

### ✉️ Integrationen
- 📥 **IMAP-Eingang** (multi-Postfach) — Anhang → Dokument → Workflow
- 📤 **Outgoing Webhooks** mit HMAC-Signatur
- 📨 **Incoming Webhooks** unter `POST /api/incoming/<token>`
- 🌐 **HTTP-Knoten** mit Body-Templates, Response-Mapping, **KI-Assistent**
- 🪟 **Microsoft 365** SSO + Benutzer-Sync (App-only Graph)
- 🔐 **SSO weiter**: OpenID Connect (Keycloak/Authentik/Auth0/Okta), Google Workspace, SAML 2.0, LDAP / Active Directory
- 🔑 **JSON-API** unter `/api/v1` mit persoenlichen API-Tokens (Abilities!)
- 📄 **PDF-Render-Knoten** (HTML → revisionssicheres Attachment)
- ✅ **Genehmigung per Mail** ueber signierten Link (kein Login noetig)
- 🤖 **KI optional und austauschbar** — OpenAI, DeepSeek, Ollama lokal

</td>
<td valign="top">

### 🛡️ Betrieb & Sicherheit
- 🚀 **Web-Installer** Schritt-fuer-Schritt — ohne SSH, ohne Composer
- 💾 **Backup & Restore** taeglich automatisch, mit **„Backup beim Installieren hochladen"** (Migration!)
- 🔄 **Update aus der UI** (Channel-Factory stable/dev, atomar, Maintenance-Flag)
- 🔐 **2FA / TOTP** optional pro Benutzer
- ⛓️ **Audit-Hashkette** (SHA-256-verkettet, jede Manipulation faellt auf)
- 💚 **Health-Seite** + JSON-Endpoint fuer Uptime-Kuma & Co.
- 📊 **Dashboard** mit eigenen Tasks, Postkorb, System-Ampel
- 👮 Rollen-/Permission-System mit Vorgesetzten-Hierarchie
- 🤝 **Vertretungsregelung**, **Workflow-Statistik**, **In-App-Notifications**

</td>
</tr>
</table>

---

## 🎬 Live-Beispiel: Rechnungseingang

Stellvertretend fuer alle Use-Cases oben — **Setup ca. 30 Minuten, dann fully automated**. Der Aufbau funktioniert mit beliebigen anderen Dokumenttypen genauso (Vertrag, Krankmeldung, Mitgliedsantrag, ...). Hier nur ein konkreter Flow zur Veranschaulichung:

```
                  ┌──────────────────────────┐
                  │  rechnungen@deine-firma  │ ← Mail mit PDF-Anhang trifft ein
                  └────────────┬─────────────┘
                               │
                               ▼ (alle 5 Min via Cron)
                  ┌──────────────────────────┐
                  │   IMAP-Postfach          │
                  │   Doku-Typ: Rechnung     │ ← Anhang wird mit SHA-256
                  │   Workflow: Rechnungs... │   gehasht + archiviert
                  └────────────┬─────────────┘
                               │
                               ▼
                  ┌──────────────────────────┐
                  │   OCR + Schema           │
                  │                          │
                  │   • Rechnungsnr (Regex)  │ ← Automatisch erkannt:
                  │   • Datum (Heuristik)    │   "R-2026-00042"
                  │   • Brutto (Heuristik)   │   "15.05.2026"
                  │   • IBAN (Heuristik)     │   "119,00 EUR"
                  │   • Kostenstelle (Liste) │   "K-2026-IT" ← deine Liste!
                  │   • USt-IdNr (Heuristik) │
                  └────────────┬─────────────┘
                               │
                               ▼
                  ┌──────────────────────────┐
                  │  Workflow "Rechnungs-    │
                  │  eingang" startet        │ ← Anhang an Instanz gehaengt
                  └────────────┬─────────────┘
                               │
                          Kostenstelle erkannt?
                               │
                  ┌────────────┴────────────┐
                  │                         │
                  ▼ JA                      ▼ NEIN
        ┌──────────────────┐      ┌──────────────────┐
        │ Lookup in Liste  │      │ Genehmigung an   │
        │ "Kostenstellen"  │      │ Rolle Buchhaltung│
        │ → Verantwortlich │      │ (Sammelpostkorb) │
        │  bekommt Mail    │      └──────────────────┘
        │  mit signiertem  │
        │  Genehmigen-/    │
        │  Ablehnen-Link   │
        └──────────────────┘
                  │
                  ▼
        Klick auf "Genehmigen" → Bestaetigungsseite → fertig.
        Kein Login. Kein App-Wechsel. 30 Sekunden.
```

> **Wichtig:** Anlernen erfolgt ueber die **Kostenstellen-Liste** die du
> ohnehin pflegst. Kein Modell-Training, kein KI-Black-Box. Aenderungen
> in der Liste wirken **sofort** beim naechsten eingehenden Dokument.

Schritt-fuer-Schritt-Anleitung → *Cookbook: Rechnungseingang einrichten*
in der Online-Hilfe.

---

## 📚 Was OWE als DMS auszeichnet

Klassische Cloud-DMS legen ein PDF ab und vergeben einen Suchbegriff.
OWE geht weiter:

| | Typisches Mini-DMS | **OWE** |
|---|---|---|
| Volltextsuche | ✓ | ✓ |
| Versionen | meist nur „neueste" | **Komplette Versionskette mit Hashes** |
| Feld-Erkennung | KI-Black-Box, Pflicht | **Heuristik + deine Lookup-Listen, KI optional** |
| Strukturierte Suche | nein | **Filter auf Rechnungsnr., Datum, Kostenstelle, Brutto** |
| Anlernen | proprietaeres Modell | **Du pflegst deine Listen, Aenderung wirkt sofort** |
| Workflow nach Upload | nein | **Postkorb + Auto-Routing + Lookup-Empfaenger** |
| Mail-Eingang direkt ins DMS | extra Tool | **Eingebaut, multi-Postfach IMAP** |
| Aufbewahrung | manuell | **DSGVO-Regeln pro Dokumenttyp, Cron-gesteuert** |
| Sharing nach extern | meist Cloud-Link | **Eigener Server, Passwort, Ablauf, Auto-Review-Mails** |
| Audit-Trail | Logs | **SHA-256-Hashkette aller Aktionen** |

> *„Documents come in, intelligence happens, the right person decides."* —
> OWE ist Ablage **und** Routing-Engine in einem System.

---

## ⚖️ OWE vs. SaaS vs. Selbst zusammenstecken

| | **🆚 SaaS (Zapier/n8n cloud/...)** | **🆚 Selbst zusammengesteckt** | **✅ Open Workflow Engine** |
|---|---|---|---|
| **Kosten** | 20-200 €/Monat pro User | „Kostenlos" (= Wochenenden) | **Hosting (5-15 €/Monat total)** |
| **Daten-Hoheit** | US-Cloud, AGB-Hoheit nicht bei dir | Bei dir | **Bei dir** |
| **DSGVO** | Schwierig (Auftragsverarbeitung etc.) | Du bist verantwortlich | **By design self-hosted** |
| **Setup-Zeit** | Account + Integration + Lernen | Wochen | **3 Minuten Web-Installer** |
| **Einrichtungs-Komplexitaet** | Klicki-bunti, aber proprietaer | Hoch (Docker, DB, Queue, …) | **FTP-Upload + Browser** |
| **Cloud-Abhaengigkeit** | Total | Variiert | **Keine** |
| **Workflow-Designer** | Ja | Selbst bauen | **Ja, Drawflow + Versionen** |
| **Eingebautes DMS** | Nein (extra Tool) | Selbst bauen | **Ja, mit OCR + Schemas** |
| **Mail-Eingang** | Meist Extra-Modul | Selbst bauen | **Ja, multi-Postfach IMAP** |
| **Update-Erfahrung** | Auto (Pflicht!) | Manuell git pull + DB | **1 Klick in der UI** |
| **Backup** | Anbieter macht's (wirklich?) | Selbst bauen | **Taeglich auto + UI-Restore** |
| **Vendor-Lock-In** | Hoch | Keiner | **Keiner — Code ist deiner** |
| **KI** | Oft Pflicht, oft teuer | Variiert | **Optional, ein-/ausschaltbar** |

---

## 🚀 Installation: nur eine Datei hochladen

> **TL;DR:** Eine einzige `installer.php` (~12 KB) per FTP in dein Webroot,
> im Browser oeffnen, fertig. Den Rest macht der Installer.

### Schritt-fuer-Schritt

#### 1️⃣ FTP-Zugang oeffnen

In deinem Hosting-Backend (1&1, Hetzner, Webgo, All-Inkl. ...) findest du
deine FTP-Daten:

```
Host:     ftp.deine-domain.de
Benutzer: u123456
Passwort: ●●●●●●●●
```

Oeffne dein FTP-Programm (FileZilla, Cyberduck, Transmit, Total Commander)
und verbinde dich.

#### 2️⃣ `installer.php` herunterladen und hochladen

Lade [`tools/owe-installer.php`](tools/owe-installer.php) aus diesem Repo
herunter (Rechtsklick → Ziel speichern unter, oder direkt aus den
GitHub-Releases). Benenne sie auf dem Weg in deinem Computer um zu
**`installer.php`** (kuerzer, einfacher zu tippen) — der Inhalt aendert
sich dadurch nicht.

Lade die Datei per FTP in **dein Webroot** hoch. Das ist der Ordner, in
dem auch normalerweise dein `index.html` oder `index.php` liegt:

| Hoster | Webroot-Pfad (typisch) |
|---|---|
| All-Inkl. · Webgo · Strato | `/` (root nach Login) oder `httpdocs/` |
| 1&1 / IONOS | `/` oder `clickandbuilds/` |
| Hetzner Webhosting | `public_html/` |
| Eigener Server | `/var/www/html/` |

> 🟢 **Wichtig:** Der Ordner muss **leer** sein (oder zumindest darf darin
> noch keine OWE-Installation existieren — der Installer prueft das und
> bricht sicher ab, wenn er was findet).

> 📌 **Webroot / Document-Root:** Laravel will den Document-Root auf den
> Unterordner `public/`. Zwei Wege:
> 1. **Sauber**: im Hosting-Backend Document-Root auf `public/` umstellen
> 2. **Fallback**: OWE liefert eine `.htaccess` im FTP-Root mit, die alles
>    intern auf `public/` umleitet (klappt auf Apache mit mod_rewrite).
>    Wird vom Bootstrap-Installer automatisch hingelegt.

#### 3️⃣ Im Browser oeffnen

```
https://deine-domain.de/installer.php
```

Du siehst die folgende Seite:

```
┌────────────────────────────────────────────────────────────┐
│                                                            │
│  Open Workflow Engine                                      │
│  Bootstrap-Installer · holt die aktuelle Version vom       │
│  Update-Proxy                                              │
│                                                            │
│  ● 1. Start    ○ 2. Download    ○ 3. Entpacken   ○ 4. ...  │
│                                                            │
│  Bootstrap-Installer                                       │
│  Dieses kleine PHP-Skript laedt die aktuelle Version der   │
│  Open Workflow Engine vom Update-Proxy update.loheide.eu,  │
│  entpackt sie hier ins Webroot und leitet anschliessend    │
│  zum eingebauten App-Installer /install weiter.            │
│                                                            │
│  Channel                                                   │
│  ◉ Stable — empfohlen fuer Produktion                      │
│  ○ Development — Vorschau, nicht produktiv nutzen          │
│                                                            │
│  Geladen von: update.loheide.eu/open-workflow-engine       │
│                                                            │
│                                       [ Loslegen → ]       │
└────────────────────────────────────────────────────────────┘
```

#### 4️⃣ „Loslegen" klicken

Das Skript:

```
✓ Channel:           stable
✓ Version geladen:   abc123def456...  (40-stelliger SHA)
✓ ZIP heruntergeladen: 24.7 MB
✓ Entpackt:          1 248 Datei(en)
✓ vendor/autoload.php gefunden — Laravel bootet.
```

#### 5️⃣ „Selbst loeschen und zum /install" klicken

Der Installer:
- 🗑️ loescht sich selbst (du musst nichts manuell entfernen)
- 🚀 leitet dich automatisch zum **App-Installer** unter `/install` weiter

#### 6️⃣ App-Installer durchlaufen

```
  ┌───────────────────────────────────────────────────────┐
  │  1. SYSTEM-CHECK                                      │
  │     PHP-Version · Pflicht-Extensions · Schreibrechte  │
  │     vendor/ vorhanden · APP_KEY                       │
  │     Ampel rot/gelb/gruen pro Punkt — Weiter erst      │
  │     wenn alles passt.                                 │
  ├───────────────────────────────────────────────────────┤
  │  2. DATENBANK                                         │
  │     SQLite (Default, ideal Shared Hosting) ◯          │
  │     MySQL / MariaDB                        ◯          │
  │     Connection-Test laeuft live.                      │
  │     Migrate + Seeder werden ausgefuehrt.              │
  ├───────────────────────────────────────────────────────┤
  │  3. ADMIN-KONTO                                       │
  │     Name · E-Mail · Passwort                          │
  │     Bekommt automatisch Rolle "admin".                │
  ├───────────────────────────────────────────────────────┤
  │  4. FERTIG                                            │
  │     Login + Naechste-Schritte-Checklist               │
  │     (SMTP · Cron · Vorlagen)                          │
  └───────────────────────────────────────────────────────┘
```

**Fertig.** Du landest mit deinem Admin-Account im Dashboard.

---

### 🔄 Umzug auf neuen Host?

**Auch nur eine Datei hochladen.** Im App-Installer-Schritt 1 waehlst du
statt *Frische Installation* die Karte **„Aus Backup wiederherstellen"**,
laedst dein altes Backup-ZIP hoch + DB-Credentials — und dein bisheriger
Admin-Account funktioniert sofort.

---

### Troubleshooting

<details>
<summary><strong>„PHP-Version zu alt"</strong></summary>

Im Hosting-Backend gibt es meist einen PHP-Versions-Schalter. Stelle
auf <strong>PHP 8.2 oder neuer</strong>.
</details>

<details>
<summary><strong>„PHP-Extension 'zip' fehlt"</strong></summary>

Bei den meisten Hostern ist <code>zip</code> bereits aktiviert. Falls
nicht: im Hosting-Backend unter „PHP-Erweiterungen" aktivieren oder
beim Hosting-Support nachfragen.
</details>

<details>
<summary><strong>„Verzeichnis nicht beschreibbar"</strong></summary>

Setze auf dem Webroot-Verzeichnis Rechte <code>755</code> oder
<code>775</code>. Im FTP-Programm: Rechtsklick auf den Ordner →
Dateirechte/Permissions.
</details>

<details>
<summary><strong>„vendor/autoload.php existiert bereits"</strong></summary>

Der Installer erkennt eine bereits vorhandene OWE-Installation und
bricht sicher ab, damit nichts ueberschrieben wird. Wenn das gewollt
ist: alte Installation per FTP komplett loeschen und neu starten.
</details>

<details>
<summary><strong>Alternative: Komplettes ZIP manuell hochladen</strong></summary>

Wenn der Bootstrap-Installer aus irgendeinem Grund nicht klappt (z. B.
Proxy nicht erreichbar, Firewall, ...) kannst du auch das Release-ZIP
manuell entpacken und alle Dateien per FTP hochladen:

```bash
# 1. Release-ZIP von GitHub entpacken (enthaelt vendor/)
# 2. Per FTP komplett auf den Webspace hochladen
# 3. Browser oeffnen → automatischer Redirect nach /install
```
</details>

> 💡 **Du musst nicht Hunderte Dateien per FTP hochladen.** Eine 12-KB-PHP-Datei
> reicht. Der Rest kommt vom Update-Proxy ueber HTTPS direkt auf deinen Server.

> 💡 **Kein Composer auf dem Server noetig.** Release-ZIPs enthalten
> `vendor/` vorgebaut. Update-System ueberspringt `composer install` falls
> `proc_open` oder die CLI auf dem Hosting nicht verfuegbar sind.

---

## 🏗️ Architektur

```
┌─────────────────────────────────────────────────────────────────┐
│                                                                  │
│   Browser  ───────────┐                                          │
│                       │                                          │
│                       ▼                                          │
│                ┌──────────────┐                                  │
│                │  Laravel 11  │ ◄──── Web-Installer (vor Setup)  │
│                └──────┬───────┘                                  │
│                       │                                          │
│       ┌───────────────┼─────────────────┬────────────────┐       │
│       ▼               ▼                 ▼                ▼       │
│  ┌─────────┐   ┌─────────────┐  ┌──────────────┐  ┌──────────┐   │
│  │Workflow │   │ Attachment  │  │   Field      │  │  Backup  │   │
│  │ Engine  │   │  Storage    │  │  Extractor   │  │ Service  │   │
│  │         │   │ SHA-256     │  │ Heuristik /  │  │  ZIP     │   │
│  │ JSON    │   │ Versionen   │  │ Regex /      │  │  DB +    │   │
│  │ Walker  │   │ OCR         │  │ Lookup / KI  │  │  Files   │   │
│  └─────────┘   └─────────────┘  └──────────────┘  └──────────┘   │
│                                                                  │
│       ┌───────────────┐  ┌─────────────┐  ┌─────────────────┐    │
│       │   Update      │  │  Workflow   │  │  Mailbox        │    │
│       │   Manager     │  │  Simulator  │  │  Fetcher (IMAP) │    │
│       │   Channel-    │  │  Trocken-   │  │  webklex/imap   │    │
│       │   Factory     │  │  lauf       │  │  (kein ext-imap)│    │
│       └───────────────┘  └─────────────┘  └─────────────────┘    │
│                                                                  │
│   storage/app/                                                   │
│    ├── attachments/<YYYY>/<MM>/<ulid>.<ext>   ← Dateien          │
│    ├── backups/owe-<datum>.zip                ← Tagessicherung   │
│    ├── .installed                             ← Installer-Marker │
│    └── .update-progress                       ← Update-Polling   │
│                                                                  │
└─────────────────────────────────────────────────────────────────┘

Stack:
  Laravel 11   ·   Tailwind CSS   ·   Alpine.js   ·   Drawflow
  SQLite (Default) oder MySQL/MariaDB
  Keine Queue, kein Redis, kein Worker noetig.
  Ein Cron-Eintrag faehrt alle Hintergrund-Tasks.
```

---

## 📋 Anforderungen

| | Mindestens |
|---|---|
| **PHP** | 8.2 oder neuer |
| **Extensions (Pflicht)** | pdo · mbstring · openssl · json · zip · fileinfo · curl · tokenizer · xml · ctype · dom |
| **Datenbank** | SQLite (empfohlen fuer Shared Hosting) **oder** MySQL ≥ 5.7 / MariaDB ≥ 10.3 |
| **Webserver** | Apache · nginx · Caddy · LiteSpeed · Shared Hosting |
| **Festplatte** | 200 MB Code + 2-10 GB fuer Anhaenge/Backups (typisch) |
| **Optional** | `pdftotext` + `pdftoppm` + `tesseract` fuer OCR · `gd` fuer Bildverarbeitung |

Lokale Tests: SQLite `:memory:`. Production: SQLite-Datei oder MySQL/MariaDB.

---

## ⏰ Cron — genau ein Eintrag

```cron
* * * * * cd /pfad/zur/app && php artisan schedule:run >> /dev/null 2>&1
```

Damit laufen automatisch:

| ⏱️ Task | Frequenz | Was |
|---|---|---|
| `workflow:check-due` | alle 5 Min | Eskalationen + Frist-Reminder |
| `workflow:run-schedules` | stuendlich | Wiederkehrende Workflows |
| `mail:fetch` | alle 5 Min | IMAP-Postfaecher abrufen |
| `tasks:remind` | taeglich 09:00 | Erinnerung fuer lange offene Tasks |
| `asset:check-due` | taeglich 06:00 | Asset-Faelligkeiten (Fuehrerschein etc.) |
| `ocr:run-pending` | taeglich 02:30 | OCR-Nachzieher |
| `documents:retention-check` | taeglich 03:15 | DSGVO-Aufbewahrungsregeln |
| `backup:run` | taeglich 01:30 | Tagessicherung (ZIP DB + Anhaenge) |
| `update:notify-available` | taeglich 08:00 | Bell-Benachrichtigung an Admins bei neuer Version |
| `shares:review` | taeglich 07:00 | Sharing-Link-Pruefung |
| `audit:cleanup` | monatlich | IP/UA aelter 2 Jahre anonymisieren |

---

## 🔁 Lifecycle ohne SSH

```
   ┌─────────────────┐    ┌──────────────────┐    ┌──────────────────┐
   │  ERSTINSTALL    │    │     UPDATE       │    │   MIGRATION      │
   ├─────────────────┤    ├──────────────────┤    ├──────────────────┤
   │                 │    │                  │    │                  │
   │  FTP-Upload     │    │  Browser →       │    │  Backup on old   │
   │  Release-ZIP    │    │  /admin/update   │    │  host (Browser)  │
   │  (mit vendor/)  │    │                  │    │                  │
   │      │          │    │  Channel auswae- │    │  ZIP downloaden  │
   │      ▼          │    │  hlen (stable)   │    │                  │
   │  Browser →      │    │                  │    │  FTP zum neuen   │
   │  /install       │    │  „Update jetzt"  │    │  Host            │
   │                 │    │                  │    │                  │
   │  Wizard:        │    │  Maintenance an  │    │  Browser →       │
   │  - System-Check │    │  Atomarer Tausch │    │  /install        │
   │  - Datenbank    │    │  composer skip   │    │                  │
   │  - Admin-Konto  │    │  artisan migrate │    │  Karte „Aus      │
   │  - Fertig       │    │  Maintenance aus │    │  Backup wieder-  │
   │                 │    │                  │    │  herstellen"     │
   │  Login          │    │  Login           │    │                  │
   │                 │    │                  │    │  ZIP hochladen   │
   │                 │    │                  │    │  + DB-Credentials│
   │                 │    │                  │    │                  │
   │                 │    │                  │    │  Login mit       │
   │                 │    │                  │    │  alten Daten     │
   │                 │    │                  │    │                  │
   └─────────────────┘    └──────────────────┘    └──────────────────┘
```

**Kein SSH, kein Composer, keine Konsole** — alles ueber den Browser.

---

## 📚 Dokumentation

Komplette Online-Hilfe in der App unter `/hilfe` (Topbar-Fragezeichen-Icon).

<details>
<summary><strong>📘 Einstieg</strong></summary>

- Erstinstallation (Web-Installer)
- Erste Schritte
- Dashboard / Startseite
- **Cookbook: Rechnungseingang einrichten** — 30-Min-Tutorial

</details>

<details>
<summary><strong>⚡ Workflows</strong></summary>

- Workflows entwerfen — Knotentypen, Bedingungen, `doc.*`-Routing
- Workflow-Vorlagen (Import/Export, 3 eingebaute Cookbooks)
- Workflow-Simulation (Trockenlauf mit Testdaten)
- Workflow-Statistik (Durchlaufzeiten, Engpaesse, Throughput)
- Vertretungsregelung (Urlaubs-Vertretung)
- Genehmigung per Mail (signierter Link)
- KI im Designer (OpenAI/DeepSeek/Ollama)
- Formulare (bedingte Felder, oeffentliche Forms)

</details>

<details>
<summary><strong>📑 Daten & Dokumente</strong></summary>

- Listen (Kostenstellen-Mappings, Lookup-Quelle fuer Schemas)
- Assets (Fuehrerschein/Unterweisung mit Wiedervorlage)
- Dokumente (Versionen, OCR, Bulk-Upload, Postkorb)
- **Felder-Schemas pro Dokumenttyp** (Heuristik · Lookup · Regex · KI)
- **Postkorb + Lookup-Routing** — `doc.*` im Workflow
- Aufbewahrungsregeln (DSGVO-Action pro Typ)
- Sharing-Links (externe Freigaben mit Auto-Review)

</details>

<details>
<summary><strong>🔌 Integrationen</strong></summary>

- HTTP-Knoten (Body-Templates, Response-Mapping, KI-Vorschlag)
- PDF-Knoten (HTML-Template → revisionssicher)
- E-Mail-Eingang (IMAP, multi-Postfach)
- Webhooks (outgoing, mit HMAC)
- Eingehende Webhooks (`/api/incoming/<token>`)
- Microsoft 365 SSO + Benutzer-Sync
- OIDC (Keycloak/Authentik/Auth0/Okta), Google Workspace, SAML 2.0, LDAP/AD-Direktbindung
- API-Tokens (persoenlich, Abilities, Ablauf)
- Secrets-Vault (Tokens verschluesselt)

</details>

<details>
<summary><strong>🛠️ Betrieb</strong></summary>

- Administration (Benutzer, Rollen, Custom Fields, Branding)
- System-Health (Statusseite + JSON-Monitoring-Endpoint)
- System-Update (Channel-Factory, atomar, Maintenance)
- **Backup & Restore** (taeglich, mit Migration-via-Installer)
- Zwei-Faktor-Anmeldung (TOTP)
- Revisionssicherheit (Audit-Hashkette, Datei-Hashes, immutable Versionen)
- Platzhalter-Referenz (alle `{{ ... }}`-Variablen)

</details>

Source: `resources/docs/*.md` — alle Inhalte sind Markdown-Dateien im Repo
und damit auch ausserhalb der App lesbar.

---

## 🗺️ Roadmap

Was schon laeuft (Stand: 303 Tests gruen):

✅ **Workflow-Engine** mit Drag-Drop-Designer, Versionen, Simulation, Statistik
✅ **Knotenpalette** Start/Ende, Approval (mit Quorum + Zusatzfeldern + Auto-Schema + Auto-Stempel), Bedingung, Switch, Aggregator, Notify, HTTP, PDF, Wait, Set-Field, Sub-Workflow, For-each-Loop
✅ **Approval-Power**: Eskalation, Vertretung, Quorum (all / n-of-m / single), Mail-Approval, konfigurierbare Zusatzfelder beim Genehmigen, **Auto-PDF-Stempel** auf der Vertragsdatei
✅ **DMS-Kern**: OCR, Versionen, Felder-Schemas, Lookup-Anlernen, Postkorb, Tags, Akten, Sharing-Links mit Auto-Review
✅ **E-Akten**: Aktendeckel buendeln Dokumente + Workflow-Vorgaenge + Vertraege + Notizen an einem Ort
✅ **Vertragsmanagement**: Vertragsarten mit eigenen Berechtigungen, pro-Vertrag-ACL, Fristen-Auto-Reminder, PDF-Anhaenge am Vertrag
✅ **Doku-UI**: Split-View mit Pfeil-Navigation + Preview-Headern + Workflow-Quick-Start, Saved-Searches (auch fuer Tasks), Bulk-Operationen, Notizen + Stempel
✅ **Mein Eingang**: vereinheitlichte Inbox mit Tabs Aufgaben / Posteingang / Wiedervorlagen + Topbar-Badge
✅ **Reports & KPIs**: Durchlaufzeiten pro Workflow, SLA-Verletzungen, Top-Verzoegerer-Knoten, Top-Empfaenger, Volumen-Trend
✅ **Dateiformate**: PDF, Bilder, ZUGFeRD/XRechnung — plus Office (DOCX/XLSX/PPTX) via LibreOffice (optional)
✅ **Integrationen**: IMAP-Eingang, Folder-Inbox, Webhooks (in + out), HTTP-Knoten, Microsoft 365 SSO, OIDC (Keycloak/Authentik/Auth0/Okta), Google Workspace SSO, SAML 2.0, LDAP/Active-Directory, Microsoft Teams Notifications, DATEV-Export, KI-Import aus curl/OpenAPI
✅ **Mobile / Kalender**: PWA mit Web-Push, iCal-Feed pro User (Outlook/Apple Calendar/Google)
✅ **Skalierung optional**: Object-Storage (S3/MinIO/Wasabi), Queue-Worker mit OCR im Hintergrund, MeiliSearch fuer Volltext bei grossen Datenmengen — komplett ueber Admin-UI konfigurierbar
✅ **API**: REST mit Token-Auth fuer Tasks, Documents, Workflows, Workflow-Instances, **Vertraege, Akten, Reports/KPIs, Audit-Log, Users, Lookup-Listen, Notifications, globale Suche**; OpenAPI/Swagger-Doku im Admin-UI
✅ **DSGVO**: Auskunft (Art. 15) + Anonymisierung (Art. 17) inkl. Audit-Trail
✅ **Compliance**: Audit-Hashkette, GoBD-Verfahrensdokumentations-Geruest, Retention-Regeln, immutable Versions
✅ **Benachrichtigungen**: pro User Event x Channel Matrix (Mail / In-App / Push), Kontext-Hilfe-Icons, Keyboard-Shortcuts (g+t/g+d/g+w/g+h/?), IT-Support-Modal
✅ **Betrieb**: Web-Installer, UI-Update, Backup/Restore, 2FA, Health, Performance-Audit, Queue-Status

Was noch kommen koennte (Diskussion willkommen):

🌐 i18n / mehrsprachige Oberflaeche (heute: Deutsch)
🔐 QES-Integration (qualifizierte elektronische Signatur)
📊 Workflow-Versions-Diff (visueller Vergleich)
🌃 Dark Mode

---

## 🤝 Mitmachen

OWE entsteht als Open-Source-Projekt fuer den deutschen Mittelstand und
Vereine. **Pull Requests und Issues sind willkommen.**

- 🐛 **Bug?** [Issue eroeffnen](../../issues)
- 💡 **Idee?** [Discussion starten](../../discussions)
- 🔧 **Code beisteuern?** Fork → Branch → PR. Bitte Tests mit liefern.
- ⭐ **Gefaellt dir?** Stern dalassen — hilft enorm.

Wenn du OWE professionell nutzt und Unterstuetzung brauchst (Anpassungen,
Integration, Support), melde dich. Open-Source-Projekt mit kommerziell
verfuegbarer Beratung — beides geht zusammen.

---

## 🧪 Tests

```bash
php artisan test
```

```
Tests:    238 passed (747 assertions)
Duration: ~10 Sekunden
```

SQLite `:memory:` in `phpunit.xml` · `RefreshDatabase` pro Test ·
Feature-Tests fuer jede Workflow-Engine-Funktion, Installer, Backup,
Quorum, IMAP, Schemas, Lookup-Extraktor, Mail-Approval, Bulk-Postkorb,
API + Webhooks.

---

## 📜 Lizenz

**MIT** — siehe [LICENSE](LICENSE). Nutzen, anpassen, weiterverkaufen
darfst du gerne. Du musst nur den Copyright-Hinweis behalten.

---

<div align="center">

### Ein DMS mit Verstand. Eine Workflow-Engine mit Wurzeln. Beides aus einer Hand.

Gebaut fuer Mittelstand und Vereine, die ihre Dokumente und Workflows behalten wollen — statt SaaS-Ketten anzuschmieden.

<sub>Wenn dir OWE Zeit oder Cloud-Kosten spart, **freuen wir uns ueber einen Stern ⭐** — und ueber Feedback noch mehr.</sub>

</div>
