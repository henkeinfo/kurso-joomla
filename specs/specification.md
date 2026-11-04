# KURSO for Joomla — Spezifikation

## Überblick

**KURSO** (https://www.kurso.de) ist ein cloudbasiertes Kursverwaltungssystem (SaaS).
Diese Joomla-Erweiterung zeigt aktuelle Kursdaten aus KURSO auf einer Joomla-Website an —
konfigurierbar, templatebasiert und benutzerfreundlich. Sie ist das Gegenstück zu
**KURSO for WordPress** (https://github.com/henkeinfo/kurso-wordpress) und folgt demselben Prinzip:
GraphQL-Abfrage + Twig-Template, Daten werden im Hintergrund abgerufen und zwischengespeichert.

Anbindung über die KURSO **GraphQL-API**:
```
https://<systemname>.kurso.de/api/graphql
```

Demo-Instanz: https://demo.kurso.de/api/graphql (User: `demo`, PW: `demodemo`)

---

## Paketaufbau

| Erweiterung | Aufgabe |
|---|---|
| `com_kurso` (Komponente) | Verwaltung von Verbindung und Abfragen, Dienste (API-Client, Cache, Twig, Ausgabe), Menüeintragstyp |
| `plg_content_kurso` | Ersetzt `{kurso query="alias"}` in Inhalten |
| `plg_editors-xtd_kurso` | Editor-Schaltfläche „KURSO“, fügt den Tag ein |
| `mod_kurso` | Modul zur Anzeige einer Abfrage in einer Modulposition |
| `plg_task_kurso` | Geplante Aufgabe „KURSO – Kursdaten aktualisieren“ |
| `pkg_kurso` | Installationspaket; aktiviert die Plugins und legt die geplante Aufgabe an |

Voraussetzungen: Joomla 5.4+ oder 6.x, PHP 8.1+, MySQL/MariaDB oder PostgreSQL.

---

## Verbindung (Komponenten → KURSO → Verbindung)

| Einstellung | Beschreibung | Pflichtfeld |
|---|---|---|
| GraphQL-URL | `https://<systemname>.kurso.de/api/graphql` (HTTPS erforderlich, HTTP nur für localhost) | Ja |
| Benutzername | KURSO-Benutzername (HTTP Basic Auth) | Ja |
| Passwort | KURSO-Passwort; leer lassen = gespeichertes Passwort behalten | Ja (beim ersten Speichern) |

- **„Verbindung testen“** prüft die im Formular eingegebenen Werte per AJAX, ohne zu speichern.
- Speicherung in der Tabelle `#__kurso_settings` (nicht in den Komponentenparametern, da der
  Joomla-Optionen-Dialog beim Speichern alle Parameter überschreibt).
- Das Passwort wird mit libsodium (secretbox) verschlüsselt; der Schlüssel wird aus `$secret` der
  `configuration.php` abgeleitet. Das Passwort wird nie an den Browser zurückgegeben. Ändert sich
  `$secret`, meldet die Erweiterung, dass das Passwort neu eingegeben werden muss.
- Berechtigung: eigene Aktion **„Verbindung bearbeiten“** (`kurso.connection`), standardmäßig nur Super User.

---

## Abfragen (Komponenten → KURSO → Abfragen)

| Eigenschaft | Beschreibung |
|---|---|
| Titel | Anzeigename im Backend |
| Alias | Eindeutiger Bezeichner für den Tag `{kurso query="alias"}` (a–z, 0–9, `-`), wird aus dem Titel erzeugt |
| GraphQL-Abfrage | Vollständiger GraphQL-Query-Text (Syntax-Highlighting) |
| Variablen (JSON) | GraphQL-Variablen; Twig-Ausdrücke werden bei jedem Abruf ausgewertet |
| Twig-Template | HTML mit Twig-Syntax (Syntax-Highlighting für HTML + Twig) |
| Aktualisierungsintervall | Minuten (1–10080) |
| Status | Veröffentlicht / Versteckt — versteckte Abfragen werden nicht ausgegeben |
| Notiz | Interne Notiz |

Liste: Status, Titel/Alias, **Einbinde-Tag mit Kopieren-Button**, Intervall, letzter Abruf
(veraltet = gelb), letzter Fehler, ID; Suche, Statusfilter, Sortierung, Seitenumbruch, Check-in/out.
Je Zeile (wie in WordPress): **Bearbeiten**, **Jetzt abrufen**, **Löschen** (mit Rückfrage).
Werkzeugleiste für mehrere ausgewählte Abfragen: Neu, Aktionen (Daten jetzt abrufen,
Veröffentlichen/Verstecken, Freigeben), Löschen, Verbindung, Optionen.

Neue Abfragen starten mit einem funktionierenden Beispiel (Kursliste als Tabelle).

### Editor

- Reiter **Abfrage & Template** (alles auf einer Seite wie in WordPress): GraphQL-Abfrage (Button
  **„In GraphiQL öffnen“**), Variablen (Button **„Auswerten“** zeigt die ausgewerteten Variablen),
  Twig-Template; rechts Status, Intervall, Notiz, Einbinde-Tag, Abrufstatus, **Beispiele laden**
  (Kursliste Tabelle, Kursliste Karten, Termine nach Kursart, Einzelkurs — jeweils Abfrage + Variablen +
  Template) und Twig-Kurzreferenz.
- Reiter **Vorschau**: **„Abfrage ausführen“** führt die ungespeicherte Abfrage live aus,
  **„Abgerufene Daten verwenden“** nutzt den Cache; links das gerenderte Template (sandboxed iframe,
  mit Frontend-CSS), rechts die Rohdaten (Template-Variablen) als JSON.
- Beim Speichern werden Variablen (JSON + Twig) und Template-Syntax geprüft; Fehler mit Zeilennummer.
  Ist die Abfrage neu oder wurden Abfrage/Variablen geändert, werden die Daten direkt nach dem Speichern abgerufen.

### Dynamische Werte in den GraphQL-Variablen

| Ausdruck | Ergebnis |
|---|---|
| `{{ date()\|date("Y-m-d") }}` | Heutiges Datum |
| `{{ date("-2 weeks")\|date("Y-m-d") }}` | Vor 2 Wochen |
| `{{ date("+1 month")\|date("Y-m-d") }}` | In einem Monat |
| `{{ date("first day of this month")\|date("Y-m-d") }}` | Erster des Monats |

Beispiel:
```json
{ "fromDate": "{{ date('-2 weeks')|date('Y-m-d') }}" }
```

---

## Datenabruf und Cache

```
[Geplante Aufgabe kurso.refresh (alle 5 Min.)]   [Seitenaufruf ohne / mit stark veralteten Daten]
                 ↓                                                  ↓
        fällige Abfragen (Intervall abgelaufen)        atomarer Claim (kein Doppelabruf)
                 ↓                                                  ↓
[GraphQL-Request mit Basic Auth an KURSO, Timeout konfigurierbar, keine Redirects]
                 ↓
[#__kurso_cache: data, fetched_at, attempted_at, error, error_at]
                 ↓
[Tag / Modul / Menüeintrag → Twig (Sandbox) → HTML-Filter → Ausgabe]
```

- Die geplante Aufgabe wird bei der Installation angelegt (Intervall 5 Minuten) und ruft alle
  veröffentlichten Abfragen ab, deren Intervall abgelaufen ist. Joomla führt Aufgaben beim Seitenaufruf
  aus („Lazy Scheduler“) oder per Cron (`php cli/joomla.php scheduler:run`).
- Fehlen Daten (erster Aufruf), werden sie beim Seitenaufruf sofort abgerufen.
- Option **„Veraltete Daten beim Seitenaufruf aktualisieren“**: Sind Daten älter als das doppelte
  Intervall (Scheduler läuft nicht), werden sie beim Seitenaufruf aktualisiert.
- **Graceful Degradation:** Ein fehlgeschlagener Abruf überschreibt die Daten nie; der Fehler wird mit
  Zeitpunkt gespeichert, in der Liste angezeigt und in `administrator/logs/com_kurso.php` protokolliert.
- Fehlversuche werden höchstens einmal pro Back-off-Zeitraum (Intervall, 1–15 Min.) wiederholt.
- **„Daten jetzt abrufen“** in der Liste ruft die ausgewählten Abfragen sofort ab.

---

## Templating (Twig)

Das Template erhält das **rohe `data`-Objekt der GraphQL-Antwort** als Kontext; die Variablen
entsprechen den Feldnamen der Abfrage. Die Erweiterung macht keine Annahmen über die Struktur.

| Ausdruck | Bedeutung |
|---|---|
| `{{ feldname }}` | Wert ausgeben (HTML-maskiert) |
| `{{ objekt.unterfeld }}` | Verschachtelter Wert |
| `{% for item in liste %}…{% else %}…{% endfor %}` | Schleife (mit Leer-Fall) |
| `{% if a > b %}…{% else %}…{% endif %}` | Bedingung |
| `{% set frei = c.maxPart - c._bookingsMeta.count %}` | Rechnen |
| `{{ wert\|date("d.m.Y") }}` | Datum (Zeitzone der Website) |
| `{{ wert\|jdate("d. F Y") }}` | Datum mit Monats-/Wochentagsnamen in der Website-Sprache; reine Datumswerte werden nicht verschoben |
| `{{ wert\|default("–") }}` | Ersatzwert |

### Sicherheit

- **Twig-Sandbox (strikt)** für Templates und Variablen. Erlaubt sind alle üblichen Tags (`if`, `for`,
  `set`, `apply`, `macro`, `import`, `with`, …), alle Core-Filter außer `invoke` sowie die Funktionen
  `attribute`, `cycle`, `date`, `max`, `min`, `random`, `range`. Gesperrt: `include`, `source`,
  `constant`, `extends`, `use`, Methodenaufrufe auf Objekten und PHP-Callables in
  `map`/`filter`/`reduce`/`sort` (nur Arrow-Functions). Ohne Sandbox wäre über
  `{{ ['id']|map('system') }}` PHP-Code ausführbar.
- **HTML-Filter** (Option, Standard an): entfernt Skripte, iFrames, Event-Handler und
  `javascript:`-URLs aus der Ausgabe (Joomla `InputFilter`, Blocklist-Modus).
- Die Vorschau im Backend rendert in einem `sandbox`-iFrame.
- Fehlermeldungen sehen nur Benutzer mit `core.manage` für KURSO (Option), Besucher nie.

Die Anmeldung öffnet im gleichen Tab (kein `target="_blank"`), da KURSO das Design der Homepage übernimmt.

---

## Ausgabe

### Tag (Inhalts-Plugin)

```
{kurso query="aktuelle-kurse"}
{kurso query="aktuelle-kurse" class="meine-css-klasse"}
```

- Funktioniert überall, wo Inhalts-Plugins laufen (Beiträge, Kategorien, „Eigene Module“ mit
  „Inhalt vorbereiten“).
- Toleriert typografische Anführungszeichen, `&quot;` und HTML aus WYSIWYG-Editoren.
- Ein `template`-Parameter wie im WordPress-Shortcode ist bewusst **nicht** vorgesehen: Autoren ohne
  KURSO-Rechte sollen keine Templates einschleusen können. Abweichende Darstellungen → Modul oder eigene Abfrage.
- Smart Search indexiert den Tag nicht.

### Editor-Schaltfläche

„KURSO“ im Editor (TinyMCE: Menü „CMS-Inhalt“) öffnet einen Dialog mit allen veröffentlichten
Abfragen (mit Suchfeld); ein Klick fügt den Tag ein. Verfügbar für Benutzer, die Beiträge oder Module
anlegen/bearbeiten dürfen oder KURSO verwalten — im Backend und beim Bearbeiten im Frontend.

### Modul „KURSO“

Parameter: Abfrage, CSS-Klasse, optionales eigenes Template (ersetzt das Template der Abfrage, z. B.
kompakte Liste für die Seitenleiste), Standard-Modulparameter (Layout, Klassen-Suffix, Caching).
Ohne Ausgabe (z. B. verborgener Fehler) wird das Modul nicht angezeigt.

### Menüeintrag „KURSO » Abfrage“

Zeigt eine Abfrage auf einer eigenen Seite (Seitenüberschrift, Seitenklasse, Metadaten, CSS-Klasse).
SEF-URLs über den Menü-Alias bzw. `/component/kurso/query/<alias>`.

### HTML-Ausgabe

```html
<div class="kurso kurso--<alias> <css-klasse>"> … gerendertes Template … </div>
```

Das Frontend-Stylesheet (abschaltbar) enthält Basis-Styles für `kurso-table`, `kurso-grid`/`kurso-card`,
`kurso-button`, `kurso-detail`, `kurso-badge--voll`. Tabellen stapeln sich in schmalen Containern
(Container Query), z. B. in einer Seitenleiste.

---

## Optionen (Komponenten → KURSO → Optionen)

| Option | Standard |
|---|---|
| Stylesheet laden | Ja |
| HTML-Ausgabe filtern | Ja |
| Fehler anzeigen für | Benutzer, die KURSO verwalten dürfen |
| Veraltete Daten beim Seitenaufruf aktualisieren | Ja |
| API-Timeout (Sekunden) | 15 |
| Berechtigungen | Joomla-ACL inkl. „Verbindung bearbeiten“ |

---

## Beispiel-Templates

Im Editor unter **Template → Beispiele** (getestet gegen das Demo-System):

1. **Kursliste (Tabelle)** — `allCourses` ab heute, freie Plätze, „Jetzt buchen“
2. **Kursliste (Karten)** — gleiche Abfrage, Kartenraster mit `jdate`
3. **Termine nach Kursart** — `allCourseTypes { courses(filter: …) }`, leere Kursarten ausgeblendet
4. **Einzelkurs** — `Course(id: $id)` mit Detailansicht

Freie Plätze: `_bookingsMeta(filter: { workflowState_not_in: ["CANCELLED"] }) { count }` in der Abfrage,
`maxPart - _bookingsMeta.count` im Template.

---

## KURSO GraphQL-API — Referenz

| Operation | Beschreibung |
|---|---|
| `allCourses(filter, first, skip, orderBy)` | Kursliste |
| `allCourseTypes(...)` | Kursarten (mit `courses`) |
| `allExams(...)` | Prüfungen |
| `Course(id)` | Einzelner Kurs |

Wichtige Felder (Course): `id`, `name`, `number`, `startDate`, `endDate`, `location { name street zip town }`,
`minPart`, `maxPart`, `onlineEnrollmentUrl`, `workflowState`, `courseType { id name }`, `_bookingsMeta { count }`.

---

## Technische Anforderungen

| Anforderung | Wert |
|---|---|
| Joomla | 5.4+ / 6.x |
| PHP | 8.1+ |
| Datenbank | MySQL/MariaDB, PostgreSQL |
| Template-Engine | Twig 3.x (gebündelt, eigener PSR-4-Autoloader, nur Twig ohne Polyfills — diese liefert Joomla) |
| HTTP | Joomla HTTP-API (`HttpFactory`) |
| JavaScript | Vanilla ES-Module, kein Build-System; CodeMirror 6 von Joomla |

---

## Offene Punkte / Spätere Erweiterungen

- [ ] Feldformatierung: Datum automatisch im deutschen Format
- [ ] Pagination langer Kurslisten
- [ ] Suchfilter im Frontend (Ort, Kursart)
- [ ] Webhook-Support: KURSO löst die Aktualisierung direkt aus (statt Polling)
- [ ] Veröffentlichung im Joomla Extensions Directory (JED)
- [ ] Mehrsprachige Templates (Joomla-Sprachzuordnung je Abfrage)
