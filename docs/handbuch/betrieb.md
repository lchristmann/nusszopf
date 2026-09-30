---
titel: Betrieb
beschreibung: Gesundheit, Logs, Warteschlange, Mail, Suchindex, Speicher und Datenbank — und was bei einem Ausfall passiert.
gruppe: betreiben
reihenfolge: 4
---

# Betrieb

Diese Seite beschreibt das laufende System: woran Du siehst, dass es gesund ist, was die Hintergrunddienste tun und was Du
bei einem Ausfall erwartest. Alle Befehle laufen auf dem Server im Verzeichnis der Installation.

## Die Dienste

| Dienst | Aufgabe |
|---|---|
| `web` | nginx. Liefert Assets und Avatare aus und reicht PHP an `php-fpm` weiter. Der einzige Dienst mit veröffentlichtem Port. |
| `php-fpm` | Die Anwendung. Migriert beim Start die Datenbank, baut die Caches auf und wendet die Sucheinstellungen an. |
| `queue-worker` | Hintergrundarbeit: **jede Mail** und die Aktualisierung des Suchindex. |
| `scheduler` | Führt geplante Aufgaben aus (siehe unten) und schreibt jede Minute einen Herzschlag. |
| `postgres` | Die Datenbank. |
| `redis` | Sitzungen, Cache und Warteschlange. Läuft mit Append-only-Datei, damit ein Absturz höchstens etwa eine Sekunde Arbeit kostet. |
| `meilisearch` | Der Suchindex. |

## Gesundheit prüfen

Es gibt drei Ebenen, von grob zu fein.

**Container.** Jeder Dienst hat einen Health-Check. Hier sollte überall `healthy` stehen:

```sh server
docker compose ps
```

**Anwendung.** Der Nusszopf prüft sich selbst: Datenbank, Redis, Suche, Scheduler, Queue-Worker und fehlgeschlagene Jobs.
Der Befehl zeigt zusätzlich die laufende Version und endet mit Exit-Code 1, wenn ein Check scheitert.

```sh server
docker compose exec php-fpm php artisan nusszopf:health
```

**Von außen.** `/health` antwortet mit `{"status":"ok"}` (200) oder `{"status":"degraded"}` (503). Richte Deinen
Uptime-Monitor darauf. Die Route startet keine Sitzung und antwortet deshalb auch, wenn Redis ausgefallen ist. Mit dem
`HEALTH_TOKEN` aus der `.env` zeigt sie außerdem Version und Grund jedes Checks:

```sh server
curl -s https://nusszopf.example.org/health
curl -s -H "Authorization: Bearer $HEALTH_TOKEN" https://nusszopf.example.org/health
```

`/up` ist die einfache Lebendprobe der Container. Sie prüft nichts hinter der Anwendung.

Was die Checks bedeuten:

- **`scheduler`, `queue`**: Beide beweisen sich mit einem Herzschlag pro Minute. Ist der letzte älter als drei Minuten,
  schlägt der Check fehl. Nach einem Neustart meldet sich das System deshalb eine Minute lang als `degraded`; das ist der
  Check bei der Arbeit, keine Störung. Der Scheduler stößt auch den Herzschlag der Queue an: Steht der Scheduler, schlagen
  beide Checks fehl.
- **`search`**: Meilisearch antwortet **und** der Index `items` existiert mit den Einstellungen aus `config/scout.php`. Ein
  verlorener oder ohne Einstellungen neu angelegter Index schlägt fehl, mit dem Hinweis `run php artisan search:reindex`.
- **`failed_jobs`**: Schlägt fehl, solange ein Auftrag, der alle Versuche aufgebraucht hat, in der Tabelle `failed_jobs`
  wartet. Der Container-Check des Workers ignoriert das, denn ein Worker, der einen Auftrag scheitern lässt, ist nicht tot.

## Logs

Die Anwendung schreibt nach stderr, also sieht man alles über Docker. Eine Log-Sammlung gibt es nicht; wer eine braucht,
richtet einen Log-Treiber in einer `compose.override.yaml` ein.

```sh server
docker compose logs -f queue-worker      # laufend mitlesen, Strg+C beendet
docker compose logs --tail 100 php-fpm   # die letzten 100 Zeilen
docker compose logs --since 1h           # alle Dienste, letzte Stunde
```

## Warteschlange und Scheduler

Über die Warteschlange laufen **alle Mails** und die **Suchindex-Updates**. Der Worker startet mit
`queue:work --tries=5 --backoff=10,30,60,120 --max-time=3600`: Ein scheiternder Auftrag wird nach 10 Sekunden, 30 Sekunden,
1 und 2 Minuten wiederholt und landet dann in `failed_jobs`. Nach einer Stunde beendet sich der Worker selbst, Docker
startet ihn sofort neu.

Der Scheduler führt zwei Aufgaben aus: die beiden Herzschläge und täglich um 03:30 UTC `newsletter:purge-unconfirmed`, das
Newsletter-Anmeldungen löscht, die 14 Tage lang niemand bestätigt hat. Mehr Aufgaben gibt es nicht. Die Anwendung läuft in UTC,
eine Zeitzoneneinstellung existiert nicht.

Fehlgeschlagene Aufträge ansehen und behandeln:

```sh server
docker compose exec php-fpm php artisan queue:failed      # welche Aufträge, mit dem Grund
docker compose exec php-fpm php artisan queue:retry all   # nach der Behebung der Ursache erneut versuchen
docker compose exec php-fpm php artisan queue:flush       # verwerfen
docker compose restart queue-worker                       # nach einem schlechten Deployment oder einem hängenden Auftrag
```

Welchen Befehl Du nimmst, hängt vom Inhalt ab. **Mails** gibt es nur in dieser Tabelle, also `queue:retry all`, sobald die
Ursache behoben ist. Bei **Suchindex-Updates** ist beides in Ordnung, denn `search:reindex` baut ohnehin alles neu auf;
dann räumt `queue:flush` nur die Tabelle auf.

Was geprüft ist: Ein Neustart des Workers mit 300 wartenden Mails verliert keine, jede kommt einmal an. Ein harter Abbruch
(`kill -9`) verliert ebenfalls nichts, aber eine Mail, die der Worker gerade hielt, kann nach 90 Sekunden ein zweites Mal
ankommen. Eine Warteschlange liefert *mindestens einmal*.

## Mail im Betrieb

Ob der Mailversand funktioniert, siehst Du nicht in der Oberfläche, sondern an `failed_jobs`: Eine Mail, die alle fünf Versuche
aufgebraucht hat, macht `nusszopf:health` rot. Der Grund steht in `queue:failed`.

| Meldung oder Symptom | Ursache und Lösung |
|---|---|
| `The <domain> domain is not verified` (Resend) | `MAIL_FROM_ADDRESS` liegt auf einer Domain, die der Anbieter nicht verifiziert hat. Verifiziere sie dort oder ändere die Adresse, dann `docker compose up -d` und `queue:retry all`. |
| Ungültiger Schlüssel oder abgelehnter Login | Derselbe Weg: Grund in `queue:failed`, Einstellung korrigieren, `up -d`, `queue:retry all`. |
| Mails landen im Spam | SPF, DKIM und DMARC der Absenderdomain richtest Du mit dem Anbieter ein. |
| Eine Mail kommt doppelt an | Der Worker wurde hart beendet, während er den Auftrag hielt. Mindestens einmal ist das Versprechen der Queue. |

Fällt der Mailanbieter aus, gelingt die auslösende Aktion trotzdem (Registrierung, Kontaktformular, Passwort vergessen), denn
Mails werden nie während der Anfrage gesendet. Sie warten und werden wiederholt.

## Newsletter-Anmeldungen

Der Nusszopf sammelt Newsletter-Anmeldungen (Double-Opt-in auf jedem Weg), **versendet aber keine Newsletter**. Historisch
wurden sie von Hand in einem externen Werkzeug geschrieben. Für den Versand exportierst Du die bestätigten Adressen:

```sh server
docker compose exec -T php-fpm php artisan newsletter:export > subscribers.csv
```

Die Spalten sind `email`, `name`, `confirmed_at`, `requested_at`, `source` (`form`, `registration` oder `profile`) und
`consent_version`.

- **Jeder Newsletter braucht einen Abmeldelink** auf `https://<deine-domain>/newsletter/unsubscribe/lead`. Abgemeldet wird in
  Nusszopf, damit die Tabelle die einzige Liste bleibt. Exportiere direkt vor jedem Versand neu, statt eine Kopie zu pflegen.
- `NEWSLETTER_CONSENT_VERSION` wird bei jeder Anmeldung mitgespeichert. Erhöhe sie, sobald sich Dein Datenschutztext ändert,
  damit klar bleibt, welchem Text jemand zugestimmt hat. Eine IP-Adresse wird nicht gespeichert.
- Unbestätigte Anmeldungen löscht der Scheduler nach 14 Tagen; von Hand geht es mit
  `php artisan newsletter:purge-unconfirmed`.
- Wer sein Konto löscht, dessen Anmeldung für dieselbe Adresse verschwindet mit.
- Bestätigungslinks sind mit `APP_KEY` signiert und sieben Tage gültig. Ein neuer `APP_KEY` macht alle verschickten Links ungültig.

## Suchindex

Der Suchindex in Meilisearch ist **abgeleitet**: Alles darin stammt aus PostgreSQL. Er wird deshalb nie gesichert, nur neu
aufgebaut, und das ist jederzeit gefahrlos möglich. Ein Befehl erledigt es:

<!-- P-11: scripts/search-recovery-test.sh runs this block exactly as printed. -->
```sh server
docker compose exec php-fpm php artisan search:reindex
```

Er tut drei Dinge in dieser Reihenfolge: Er wendet die Indexeinstellungen aus `config/scout.php` an (welche Felder
durchsucht werden, den Kategoriefilter, die Sortierung, das Trefferlimit), er verwirft alle Dokumente, und er importiert alle
öffentlichen Projekte samt Gesuchen neu. Private Projekte gelangen nie in den Index. Die Dokumente schreibt der Worker, die
Suche füllt sich also über einige Sekunden bis Minuten. Der Befehl wartet bis zu 30 Sekunden auf den Worker und sagt dann,
wie es steht.

Bevor er etwas ändert, prüft er, ob die Warteschlange den Import annehmen kann. Ist Redis nicht erreichbar, bricht er mit
`nothing was changed` ab und lässt einen funktionierenden Index unangetastet.

**Wann Du ihn ausführst:**

- nach einer Wiederherstellung;
- nachdem das Volume `meilisearch-data` verloren ging oder ersetzt wurde;
- wenn der Changelog einer Version sagt, dass sich die Suchdokumente geändert haben;
- wenn `nusszopf:health` bei `search` `run php artisan search:reindex` verlangt;
- wenn Treffer fehlen oder veraltet sind, zum Beispiel nachdem Meilisearch länger ausgefallen war, als die Wiederholungen dauern.

Der Health-Check bemerkt einen fehlenden Index und fehlende Einstellungen, aber kein einzelnes fehlendes Dokument. Sehen die
Ergebnisse falsch aus, führe den Befehl aus.

**Wenn Meilisearch selbst nicht startet.** `docker compose ps` zeigt `meilisearch` im Neustart-Kreis, das Log enthält etwa
`MDB_INVALID: File is not an LMDB file`: Die Daten auf der Platte sind beschädigt, oder eine neue Version kann die alten nicht lesen.
Wirf die Daten weg und baue sie neu auf. PostgreSQL, hochgeladene Dateien und Sitzungen bleiben unberührt.

<!-- P-11: scripts/search-recovery-test.sh runs this block exactly as printed. -->
```sh server
docker compose rm --stop --force meilisearch
docker volume rm nusszopf_meilisearch-data
docker compose up -d --wait
docker compose exec php-fpm php artisan search:reindex
```

Solange der Index fehlt, funktioniert die Seite weiter, die Suche zeigt nur keine Treffer. Zwischenzeitlich gespeicherte
Projekte sind nicht verloren: Sie stehen in PostgreSQL, und der Reindex holt sie.

## Speicher und Medien

Hochgeladen werden nur **Avatare**. Sie liegen im Volume `laravel-storage` unter `storage/app/public/avatars`, eine Datei pro Upload.
`web` liefert sie direkt als statische Dateien mit langer Cache-Zeit aus; ein ausgetauschter Avatar bekommt immer eine neue
URL. Das Volume gehört zu jedem [Backup](backup.md). Ein Löschen des Volumes löscht alle Avatare.

Uploads sind auf 10 MB begrenzt (nginx und PHP setzen dieselbe Grenze). Ein Objektspeicher (S3) ist nicht vorgesehen.

## Datenbank

Der Nusszopf braucht keine Datenbankpflege im Alltag. Zum Nachsehen ohne Risiko genügen lesende Abfragen:

```sh server
docker compose exec -T postgres sh -c 'psql -U "$POSTGRES_USER" -d "$POSTGRES_DB" -c "select count(*) as projekte from projects"'
docker compose exec php-fpm php artisan migrate:status    # welche Migrationen angewendet sind
```

Die wichtigsten Tabellen sind `users`, `projects`, `project_requests` (die Gesuche), `project_analytics` (Aufrufzähler),
`leads` (Newsletter) und `failed_jobs`. Ändere Daten nicht von Hand: Der Suchindex und die Berechtigungen laufen über
die Anwendung, ein direkter SQL-Eintrag umgeht beides.

## Wenn ein Dienst ausfällt

Geprüft auf dem Produktions-Stack, indem jeder Dienst gestoppt, gearbeitet und wieder gestartet wurde.

| Ausfall | Was Besucher sehen | Was das System tut | Behebung |
|---|---|---|---|
| **Meilisearch** | Seiten funktionieren, die Suche zeigt keine Treffer („Verzopft…“); ein zwischenzeitlich gespeichertes Projekt ist noch nicht auffindbar. | `/health` meldet 503 (`search`). Index-Aufträge werden wiederholt und landen nach etwa 3½ Minuten in `failed_jobs`. | Meilisearch starten. Aufträge in `failed_jobs`: `queue:retry all` oder einfach `search:reindex`. |
| **Redis** | Jede Seite ist ein 500 (Sitzungen liegen dort). `/up` bleibt 200. | `/health` meldet 503 und antwortet trotzdem. Der Worker startet ständig neu, der Scheduler loggt jede Minute einen Fehler. Was vorher in der Warteschlange war, geht nicht verloren. | Redis starten, alles läuft von selbst weiter. |
| **PostgreSQL** | Seiten, die Daten lesen, scheitern; die Suchseite lädt noch. | `/health` meldet 503 (`database`), `php-fpm` bleibt „healthy“. | PostgreSQL starten. |
| **Queue-Worker** | Alles funktioniert, aber Mails gehen nicht raus und Suchänderungen erscheinen nicht. | Aufträge warten in Redis. Nach drei Minuten schlägt `queue` fehl. | `docker compose up -d queue-worker`. Die wartenden Aufträge laufen sofort, der Reihe nach. |
| **Scheduler** | Alles funktioniert, nur die tägliche Bereinigung und die Herzschläge fehlen. | Nach drei Minuten schlagen `scheduler` **und** `queue` fehl. | `docker compose up -d scheduler`. Verpasste Läufe werden nicht nachgeholt; die Bereinigung löscht nach Alter, der nächste Lauf holt auf. |
| **Mailanbieter** | Die auslösende Aktion gelingt weiterhin. | Fünf Versuche, dann `failed_jobs`. | Ursache beheben (Schlüssel, verifizierte Domain, Relay), dann `queue:retry all`. |
| **Google** (falls aktiv) | Beim Login eine Meldung „Sorry, da lief etwas schief.“; es entsteht kein Konto. | Die Anfrage scheitert sofort, ein Wiederholen gibt es nicht. | Besucher versuchen es erneut oder melden sich mit Passwort an. |

Eine gespeicherte Änderung geht nie verloren, weil der Suchindex gerade fehlt: Die Datenbank wird zuerst geschrieben, das
Indexieren danach wiederholt, und `search:reindex` repariert, was trotzdem schiefging.

## Einmalige Artisan-Befehle

```sh server
docker compose exec php-fpm php artisan <befehl>
```

Die eigenen Befehle des Nusszopf sind `nusszopf:health`, `search:reindex`, `newsletter:export`,
`newsletter:purge-unconfirmed` und, nur im Demo-Modus, `demo:reset`. Für den allerersten `APP_KEY` (vor dem ersten Start)
brauchst Du stattdessen einen frischen Container, weil noch nichts zwischengespeichert ist:

```sh server
docker compose run --rm --no-deps --entrypoint php php-fpm artisan key:generate --show
```

## Weiter

Bei Problemen: [Fehlerbehebung](fehlerbehebung.md). Daten sichern und zurückholen: [Backup und Wiederherstellung](backup.md).
