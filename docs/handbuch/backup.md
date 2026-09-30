---
titel: Backup und Wiederherstellung
beschreibung: Was gesichert wird und was nicht, das Backup-Skript für cron, und wie Du auf einem leeren Server alles zurückholst.
gruppe: betreiben
reihenfolge: 5
---

# Backup und Wiederherstellung

Ein Backup des Nusszopf besteht aus drei Dateien und ist mit einem Skript in wenigen Sekunden gemacht, während die Seite
weiterläuft. Genauso wichtig ist das Gegenstück: Ein Backup, das Du nie zurückgespielt hast, ist keins. Das Zurückspielen
auf einem leeren Server ist geprüft (mit dem Skript und den Befehlen dieser Seite, unverändert ausgeführt); übe es trotzdem
gelegentlich auf einem Ersatzrechner.

## Was gesichert wird

| Datei | Inhalt | Warum |
|---|---|---|
| `postgres.dump` | Die ganze Datenbank: Konten mit Passwort-Hashes, Projekte, Gesuche, Aufrufzähler, Newsletter-Anmeldungen samt Einwilligung, offene Passwort-Links, fehlgeschlagene Aufträge | Das sind die Daten der Anwendung. |
| `storage.tar.gz` | Das Volume `laravel-storage`: die Avatare und temporäre Uploads | Hochgeladene Dateien gibt es sonst nirgends. |
| `installation.tar.gz` | Das Installationsverzeichnis: `.env`, `docker-compose.yaml`, `legal/`, eine `compose.override.yaml` und was sonst dort liegt | Die `.env` enthält den `APP_KEY` (ohne ihn funktionieren verschickte Links nicht mehr) und die Passwörter, mit denen die Datenbank angelegt wurde. `NUSSZOPF_VERSION` darin sagt, zu welcher Version der Dump gehört. |

**Bewusst nicht gesichert:**

- **Der Suchindex** (`meilisearch-data`) ist abgeleitet. Die Wiederherstellung baut ihn mit `search:reindex` neu auf.
- **Redis** (`redis-data`): Sitzungen, wartende Aufträge, Cache. Nach einer Wiederherstellung müssen sich alle neu anmelden,
  und ein Auftrag, der beim Backup noch wartete (etwa eine Mail), läuft nicht. Eine fehlgeschlagene Mail steht in
  `failed_jobs`, also in der Datenbank.
- **Die Docker-Images.** Sie werden für die Version aus der `.env` neu geladen.
- **Dein Reverse Proxy und seine Zertifikate.** Sichere sie mit dem Proxy.

## Das Backup-Skript

Speichere es als `/usr/local/bin/nusszopf-backup.sh` und mache es mit `chmod 700` ausführbar. Liegt Deine Installation nicht
in `/opt/nusszopf`, ändere `NUSSZOPF_DIR`.

<!-- P-10: scripts/restore-test.sh runs this block exactly as written. -->
```sh server
#!/bin/sh
# Nusszopf backup: the database, the uploaded files and the installation directory (Handbuch: Backup und Wiederherstellung).
set -eu
NUSSZOPF_DIR=/opt/nusszopf        # your installation: docker-compose.yaml and .env
BACKUP_ROOT=/opt/nusszopf-backups
KEEP_DAYS=30

umask 077                         # a backup holds .env, with APP_KEY and the passwords
cd "$NUSSZOPF_DIR"                # cron starts elsewhere, and docker compose must run here
BACKUP_DIR="$BACKUP_ROOT/$(date +%Y-%m-%d_%H-%M-%S)"
mkdir -p "$BACKUP_DIR.incomplete"

# The database first: a file uploaded while the rest runs is then an unused extra, never a missing avatar.
docker compose exec -T postgres sh -c 'pg_dump --format=custom -U "$POSTGRES_USER" -d "$POSTGRES_DB"' > "$BACKUP_DIR.incomplete/postgres.dump"
docker compose exec -T postgres pg_restore --list < "$BACKUP_DIR.incomplete/postgres.dump" > /dev/null
docker compose run --rm --no-deps -T --entrypoint tar php-fpm czf - -C /var/www/storage/app . > "$BACKUP_DIR.incomplete/storage.tar.gz"
tar tzf "$BACKUP_DIR.incomplete/storage.tar.gz" > /dev/null
tar czf "$BACKUP_DIR.incomplete/installation.tar.gz" .

mv "$BACKUP_DIR.incomplete" "$BACKUP_DIR"
find "$BACKUP_ROOT" -mindepth 1 -maxdepth 1 -type d -mtime +"$KEEP_DAYS" -exec rm -rf {} +
echo "Backup written to $BACKUP_DIR"
```

Starte es täglich per cron. Öffne die Crontab von root mit `crontab -e` und trage zum Beispiel ein, dass es jede Nacht um
3 Uhr läuft:

```text
0 3 * * * /usr/local/bin/nusszopf-backup.sh >> /var/log/nusszopf-backup.log 2>&1
```

Was Du wissen solltest:

- Das Skript braucht den laufenden Stack, denn es fragt PostgreSQL nach dem Dump.
- **Ein fehlgeschlagenes Backup ist sichtbar:** Das Skript bricht beim ersten Fehler mit einem Exit-Code ungleich null ab, das
  Log nennt den Grund, und der Ordner behält die Endung `.incomplete`. Nur ein Ordner ohne diese Endung ist ein vollständiges
  Backup. Schau ab und zu ins Log und in die Ordnerliste, denn von allein warnt niemand.
- **Kopiere die Backups auf einen anderen Rechner.** Ein Backup auf derselben Platte überlebt den Verlust des Servers nicht.
  Kopiere `/opt/nusszopf-backups` zum Beispiel per `rsync` aus der Crontab eines anderen Rechners. Die Ordner sind nur für root
  lesbar; behandle auch die Kopien als geheim, denn sie enthalten die `.env`.
- Der Dump ist in sich konsistent. Die Dateien werden einen Moment später kopiert; ein dazwischen hochgeladener Avatar ist im
  Backup, wird aber nicht verwendet.
- Aufbewahrt werden 30 Tage (`KEEP_DAYS`).

## Wiederherstellen

Die Befehle stellen Datenbank, Dateien und Installationsverzeichnis genau so wieder her, wie sie zum Zeitpunkt des Backups
waren, und bauen den Suchindex neu auf. Alles, was danach geschrieben wurde, geht verloren. Dieselben Befehle dienen auch als
[Rollback nach einem Deployment](deployment.md#zurückgehen-rollback).

**1. Vorbereiten.**

- *Auf einem neuen Server:* Installiere Docker Engine mit dem Compose-Plugin (wie bei der [Installation](installation.md)),
  kopiere den Backup-Ordner dorthin und lege das Verzeichnis an: `mkdir -p /opt/nusszopf`.
- *Auf der bestehenden Installation* (Rollback oder ein älteres Backup): Stoppe sie mit `docker compose down`, **ohne** `-v`.

**2. Backup wählen.** Ein vollständiger Ordner, also ohne `.incomplete`:

```sh server
RESTORE_DIR=/opt/nusszopf-backups/2026-09-25_03-00-00
```

**3. Zurückspielen**, als root:

<!-- P-10: scripts/restore-test.sh runs this block exactly as written. -->
```sh server
cd /opt/nusszopf
tar xzf "$RESTORE_DIR/installation.tar.gz"
docker compose pull
docker compose up -d --wait postgres redis
docker compose exec -T redis redis-cli FLUSHALL
docker compose exec -T postgres sh -c 'dropdb --if-exists -U "$POSTGRES_USER" "$POSTGRES_DB" && createdb -U "$POSTGRES_USER" "$POSTGRES_DB"'
docker compose exec -T postgres sh -c 'pg_restore --exit-on-error -U "$POSTGRES_USER" -d "$POSTGRES_DB"' < "$RESTORE_DIR/postgres.dump"
docker compose run --rm --no-deps -T --entrypoint sh php-fpm -c 'find /var/www/storage/app -mindepth 1 -delete && tar xzf - -C /var/www/storage/app' < "$RESTORE_DIR/storage.tar.gz"
docker compose up -d --wait
docker compose exec php-fpm php artisan search:reindex
docker compose exec php-fpm php artisan nusszopf:health
```

Was die Zeilen tun:

- `tar xzf …installation.tar.gz` bringt `.env`, `legal/`, Deine `compose.override.yaml` und die `docker-compose.yaml` der Version
  zurück, mit der das Backup entstand. `docker compose pull` holt dann deren Images, damit Code und Datenbank zusammenpassen.
- Nur PostgreSQL und Redis laufen, während die Daten zurückkehren, damit nichts dazwischen schreibt.
- `FLUSHALL` leert Redis. Aufträge nach dem Backup gehören zu Daten, die es nicht mehr gibt, und Sitzungen werden abgemeldet.
- `dropdb`/`createdb` beginnen mit einer leeren Datenbank. Nimm **nicht** `pg_restore --clean` stattdessen: Nach einem Upgrade
  hat die Datenbank Tabellen, die das Backup nicht kennt. `--clean` scheitert dann auf halbem Weg und hinterlässt ein Gemisch
  aus zwei Versionen, das sich nicht mehr upgraden lässt.
- Das Storage-Volume wird geleert und über das `php-fpm`-Image gefüllt, damit die Dateien ihren Besitzer behalten.
- `up -d --wait` startet alles und kehrt zurück, sobald jeder Dienst gesund ist (etwa eine Minute). `php-fpm` migriert nichts,
  denn der Code ist der des Backups. `search:reindex` füllt den Suchindex über den Worker in Sekunden bis Minuten neu.

Scheitert eine Zeile, behebe die Ursache (zum Beispiel braucht `docker compose pull` Netz) und führe **alle Zeilen von vorn**
aus. Jede lässt sich wiederholen.

**Danach gilt** (so geprüft): Die Anmeldung mit den Passwörtern aus dem Backup funktioniert, aber alle müssen sich neu anmelden.
Vor dem Backup verschickte Links funktionieren weiter (Passwort zurücksetzen, E-Mail bestätigen, Newsletter bestätigen).
Öffentliche Projekte werden wieder gefunden, private nicht. Die Avatare werden ausgeliefert. Worker und Scheduler melden
sich binnen einer Minute gesund.

Willst Du danach auf eine neuere Version, spiele sie wie gewohnt ein: [Deployment](deployment.md).

## Warum das Skript so aussieht

Frühere Fassungen dieses Skripts scheiterten, ohne es zu sagen: Die Dump-Zeile las `$DB_DATABASE` aus einer Shell, die es nicht
hat, und unter cron, das nicht im Installationsverzeichnis startet, fand jedes `docker compose` keine Datei und schrieb einen
leeren Dump, während das Skript mit Erfolg endete. Die Fassung oben behebt beides (`set -eu`, `cd "$NUSSZOPF_DIR"`, ein
`pg_restore --list` als Test jedes Dumps). Kopiere deshalb keine älteren Varianten und ändere die Prüfzeilen nicht.
