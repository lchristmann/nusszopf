---
titel: Lokale Entwicklung
beschreibung: Der erste Start in Docker, die Befehle für den Alltag und die Stolpersteine, über die man tatsächlich fällt.
gruppe: entwickeln
reihenfolge: 1
---

# Lokale Entwicklung

Die Entwicklung läuft komplett in Docker. Auf Deinem Rechner brauchst Du **Docker, Docker Compose und Git**, aber weder PHP noch
Composer, Node, PostgreSQL, Redis oder Meilisearch. Alles, was PHP, Composer, Artisan oder npm betrifft, läuft im Container
`workspace`. Diese eine Ausführungsumgebung gilt für alle Mitwirkenden und für KI-Assistenten gleichermaßen.

Der Stack für die Entwicklung (`compose.dev.yaml`) ist ein anderer als der für den Betrieb (`docker-compose.yaml`). Gib deshalb
für die Entwicklung immer `-f compose.dev.yaml` an.

> [!NOTE]
> Alle Befehle dieser Seite laufen im Repository auf Deinem Rechner. Wer in den `workspace`-Container wechselt
> (`docker compose -f compose.dev.yaml exec workspace bash`), lässt das Präfix `docker compose -f compose.dev.yaml exec workspace`
> weg.

## Der erste Start

Klone das Repository und arbeite die Zeilen der Reihe nach ab. Wenn Deine Benutzer-ID nicht `1000:1000` ist
(`id -u` und `id -g` zeigen sie), trage `UID` und `GID` in die `.env` ein, bevor Du zum ersten Mal startest, damit Dateien, die
Container anlegen, Dir gehören.

```sh dev
git clone https://github.com/lchristmann/nusszopf.git && cd nusszopf
cp .env.example .env
sed -i "s|^MEILISEARCH_KEY=.*|MEILISEARCH_KEY=nusszopf-dev-master-key-change-me|" .env
sed -i "s|^APP_KEY=.*|APP_KEY=base64:$(head -c 32 /dev/urandom | base64)|" .env
docker compose -f compose.dev.yaml up -d --build
docker compose -f compose.dev.yaml exec -u root workspace chown "$(id -u):$(id -g)" /var/www/vendor /var/www/node_modules
docker compose -f compose.dev.yaml exec workspace composer install
docker compose -f compose.dev.yaml exec workspace npm install
docker compose -f compose.dev.yaml exec workspace php artisan storage:link
docker compose -f compose.dev.yaml exec workspace php artisan migrate --seed
docker compose -f compose.dev.yaml exec workspace php artisan search:reindex
docker compose -f compose.dev.yaml exec workspace npm run build
```

Drei dieser Zeilen gibt es wegen der Entwicklungsumgebung, nicht wegen des Nusszopf:

- **`APP_KEY` vor dem ersten `up`.** Compose reicht die `.env` als Umgebung an alle Container weiter, und ein leerer `APP_KEY`
  dort gewinnt gegen einen Schlüssel, den `php artisan key:generate` später in die Datei schreibt. Die Container liefen ohne
  Schlüssel weiter, bis sie neu erstellt würden. Darum wird der Schlüssel zuerst erzeugt.
- **`MEILISEARCH_KEY`.** In `.env.example` ist er leer. Der Dienst `meilisearch` nimmt dann zwar einen Standard-Schlüssel, die
  Anwendung liest aber den leeren Wert aus der `.env` und schickt keinen, sodass jeder Suchbefehl wegen eines fehlenden
  `Authorization`-Headers abgelehnt wird. Steht derselbe Schlüssel in der `.env`, haben beide denselben. Die Tests der Gruppe
  `meilisearch` und die Playwright-Suchtests brauchen ihn auch.
- **`chown`.** `vendor` und `node_modules` sind benannte Volumes, und ein neues Docker-Volume gehört root. `composer install`
  scheitert sonst mit `Permission denied`. Der `chown` (einmalig, als root) übergibt beide an Deinen Benutzer.

`search:reindex` wendet die Indexeinstellungen an und füllt den Index in einem Schritt.

Danach öffnest Du <http://localhost:8080> (oder Deinen `APP_PORT`) und registrierst ein normales Konto. Es gibt keinen
Admin-Schritt: Alle Konten sind gleichberechtigt, auch in der Entwicklung. Jede Mail, die die Anwendung verschickt, landet
nicht im Internet, sondern beim Mailpit-Fänger unter <http://localhost:8025>.

## Die Dienste

| Dienst | Aufgabe |
|---|---|
| `web`, `php-fpm` | nginx und die Anwendung, mit dem Quellcode als Bind-Mount, also ohne Neubau bei Änderungen |
| `workspace` | Deine Arbeitsumgebung: PHP, Composer, Node, Artisan |
| `queue-worker` | `queue:listen`, damit Codeänderungen ohne Neustart wirken |
| `scheduler` | `schedule:work` |
| `postgres`, `redis`, `meilisearch` | wie im Betrieb; PostgreSQL enthält zusätzlich die Testdatenbank `nusszopf_testing` |
| `mailpit` | fängt jede Mail ab, Oberfläche auf Port 8025. Nur Entwicklung und CI, nie im Betrieb |
| `locationiq-stub` | ersetzt die Ortssuche-API; für den echten Dienst `LOCATIONIQ_KEY` und `LOCATIONIQ_URL` in die `.env` |
| `playwright` | Browser-Tests. Debian-basiert, weil Playwrights Browser in den Alpine-Images der Anwendung nicht zuverlässig laufen |

## Die Befehle für jeden Tag

| Aufgabe | Befehl |
|---|---|
| In den Container wechseln | `docker compose -f compose.dev.yaml exec workspace bash` |
| Artisan | `docker compose -f compose.dev.yaml exec workspace php artisan <befehl>` |
| Composer | `docker compose -f compose.dev.yaml exec workspace composer <befehl>` |
| Frontend-Server (Vite) | `docker compose -f compose.dev.yaml exec workspace npm run dev` |
| Frontend bauen | `docker compose -f compose.dev.yaml exec workspace npm run build` |
| Formatieren | `docker compose -f compose.dev.yaml exec workspace composer pint` |
| Formatierung prüfen (wie CI) | `docker compose -f compose.dev.yaml exec workspace composer lint:check` |
| Statische Analyse | `docker compose -f compose.dev.yaml exec workspace composer larastan` |
| Unit- und Feature-Tests | `docker compose -f compose.dev.yaml exec workspace composer test` |
| Browser-Tests | `docker compose -f compose.dev.yaml exec playwright npx playwright test` |
| Datenbank frisch aufsetzen | `docker compose -f compose.dev.yaml exec workspace php artisan migrate:fresh --seed` |
| Suchindex neu aufbauen | `docker compose -f compose.dev.yaml exec workspace php artisan search:reindex` |
| Logs | `docker compose -f compose.dev.yaml logs -f [dienst]` |

Worker und Scheduler laufen schon als eigene Dienste, Du musst sie nicht starten. Wie die Tests aufgebaut sind, steht unter
[Tests und Qualität](tests.md); die Prüfskripte für Produktions-Images und Releases (`scripts/smoke-test.sh`,
`scripts/upgrade-test.sh` und andere) dort ebenfalls.

## Wenn etwas hakt

Diese Probleme sind tatsächlich aufgetreten.

| Symptom | Ursache und Lösung |
|---|---|
| `composer install` oder `npm install` scheitert mit `Permission denied` in `vendor` oder `node_modules` | Die Volumes gehören root: `docker compose -f compose.dev.yaml exec -u root workspace chown "$(id -u):$(id -g)" /var/www/vendor /var/www/node_modules` |
| Suchbefehle oder Tests der Gruppe `meilisearch` werden wegen eines fehlenden `Authorization`-Headers abgelehnt | `MEILISEARCH_KEY` in der `.env` ist leer. Setze ihn wie oben und führe `docker compose -f compose.dev.yaml up -d` aus, denn ein Container behält die Umgebung, mit der er erstellt wurde. |
| „No application encryption key has been specified“ | Die Container wurden mit leerem `APP_KEY` erstellt. Mit dem Schlüssel in der `.env`: `docker compose -f compose.dev.yaml up -d --force-recreate`. |
| Eine geänderte `.env` wirkt nicht | Erstelle die Container neu: `docker compose -f compose.dev.yaml up -d`. `restart` behält die alte Umgebung. |
| Avatare antworten mit 404 | `php artisan storage:link` (einmalig; `public/storage` ist git-ignoriert). |
| Newsletter- oder Passwort-Vergessen-Tests scheitern beim zweiten vollen Lauf innerhalb von 15 Minuten | Das Kontingent pro Adresse ist verbraucht: `php artisan cache:clear`, dann erneut laufen lassen. |
| Ein Suchtest scheitert nur bei mehreren gleichzeitigen Browser-Engines | Der Index-Wiederherstellungs-Test leert den einen gemeinsamen Index. Lokal die Engines nacheinander laufen lassen; CI startet je Engine einen eigenen Stack. |
| Die visuellen Tests oder der `PerformanceDatasetSeeder` haben Deine Entwicklungsdaten ersetzt | Beide setzen die Entwicklungsdatenbank absichtlich zurück. `php artisan migrate:fresh --seed` bringt den normalen Seed zurück. |

## Mit einem KI-Assistenten arbeiten

Ein Assistent wie Claude Code soll zuerst `CLAUDE.md`, die Dateien unter `.claude/rules/` und die betroffene Fachseite unter `docs/`
lesen; bei Fragen zu Produkt, Domäne oder Oberfläche zusätzlich die historischen Quellen (die archivierten Repositories, [Konventionen](konventionen.md#wo-die-wahrheit-liegt)). Diese
Regeln stehen in `CLAUDE.md` in Form, die solche Werkzeuge lesen; [Konventionen](konventionen.md) erklärt sie für Menschen.
