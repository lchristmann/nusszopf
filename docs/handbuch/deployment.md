---
titel: Deployment
beschreibung: Eine neue Version einspielen — der normale Ablauf, was dabei automatisch passiert, wie Du es prüfst und wie Du zurückgehst.
gruppe: betreiben
reihenfolge: 3
---

# Deployment

Ein Deployment des Nusszopf heißt: eine neue **Version einspielen**. Es gibt keinen Build auf dem Server und kein
Hochladen von Code. Jede Version ist ein Paar fertiger Docker-Images (`nusszopf-php-fpm` und `nusszopf-web`) mit
passenden Dateien für den Betrieb. Du sagst dem Stack, welche Version laufen soll, und Docker erledigt den Rest.

Der Ablauf besteht aus drei Befehlen, einer Sicherung davor und einer Prüfung danach. Die Sicherung ist keine Formsache:
Eine Datenbankmigration lässt sich nicht rückgängig machen, indem man ein älteres Image startet. Der Weg zurück ist das Backup.

## Bevor Du anfängst

- Du bist per SSH auf dem Server, im Verzeichnis der Installation (dort liegen `docker-compose.yaml` und `.env`).
- Du hast die [Änderungen](https://github.com/lchristmann/nusszopf/blob/main/CHANGELOG.md) **jeder Version zwischen Deiner und
  der neuen** gelesen. Achte auf die Marker **Breaking:** und **Migration required:**. Sie stehen dort, wo Du außer den
  drei Befehlen unten noch etwas tun musst.
- Das [Backup-Skript](backup.md#das-backup-skript) ist eingerichtet und läuft.

Mehrere Versionen zu überspringen ist erlaubt. Spiele einfach die neueste ein und lies alle Einträge dazwischen.

## Der normale Ablauf

**1. Sichern.** Führe das Backup-Skript aus und notiere den Ordner, den es nennt. Er enthält Datenbank, hochgeladene
Dateien und die Installation mit der `.env` und `docker-compose.yaml` der jetzigen Version. Das ist alles, was ein Rollback
braucht.

```sh server
/usr/local/bin/nusszopf-backup.sh      # Ausgabe: "Backup written to /opt/nusszopf-backups/…"
```

**2. Die neue Version vorbereiten.** Ersetze `1.0.1` durch die Version, die Du einspielen willst. Das Skript ersetzt
`docker-compose.yaml` durch die der neuen Version, setzt `NUSSZOPF_VERSION` in der `.env` und lässt alles andere in der `.env`
unangetastet. Die bisherigen Dateien bleiben als `*.previous` daneben liegen.

```sh server
curl -fsSLO https://github.com/lchristmann/nusszopf/releases/download/1.0.1/install.sh
sh install.sh --upgrade 1.0.1
```

Ohne Versionsangabe (`sh install.sh --upgrade`) nimmt das Skript die neueste **stabile** Version; ein Vorab-Release musst
Du immer beim Namen nennen. Am Ende listet das Skript, was in Deiner `.env` fehlt oder nicht mehr sicher ist:
leere Pflichteinstellungen, veraltete Werte wie `TRUSTED_PROXIES=*` und neue Einstellungen, für die ihre Standardwerte
gelten. Bearbeite die `.env`, bevor Du weitermachst, falls etwas aufgeführt ist.

**3. Neu starten.**

```sh server
docker compose pull
docker compose up -d
```

`pull` lädt die Images der neuen Version. `up -d` erstellt genau die Container neu, deren Image oder Einstellungen sich
geändert haben. Die Volumes mit Datenbank, Dateien, Suchindex und Redis bleiben erhalten.

**4. Prüfen.**

```sh server
docker compose ps                                        # alle Dienste "healthy"
docker compose exec php-fpm php artisan nusszopf:health  # zeigt die neue Version, alle Checks "ok"
```

Rufe außerdem die Seite im Browser auf und melde Dich an. Was genau „gesund“ bedeutet und warum `queue-worker` und
`scheduler` ein bis drei Minuten brauchen, steht unter [Betrieb](betrieb.md#gesundheit-prüfen).

## Was beim Neustart automatisch passiert

Du musst nichts davon von Hand tun. Der Reihe nach:

| Schritt | Was geschieht |
|---|---|
| **Migrationen** | Der Einstiegspunkt von `php-fpm` führt ausstehende Datenbankmigrationen aus (`migrate --force --isolated`) und ist dabei gesperrt, sodass sie nur einmal laufen. Erst danach bedient der Container Anfragen. |
| **Caches** | Danach baut derselbe Einstiegspunkt die Caches für Konfiguration, Routen, Views und Events neu auf. Ein `cache:clear` ist nie nötig. |
| **Suchindex** | Die Einstellungen des Suchindex (Filter, Sortierung, Trefferlimit) werden angewendet. Ist Meilisearch nicht erreichbar, gibt es nur eine Warnung, der Start scheitert daran nicht. |
| **Assets** | Sie stecken fertig gebaut in den Images. `web` und `php-fpm` einer Version passen immer zusammen; einen Build-Schritt oder ein geteiltes Assets-Volume gibt es nicht. |
| **Worker und Scheduler** | `queue-worker` und `scheduler` starten erst, wenn `php-fpm` gesund ist, also nachdem die Migrationen durch sind. Aufträge, die noch in Redis warteten, arbeitet die neue Version ab. |
| **Sitzungen und Links** | Sitzungen liegen in Redis, angemeldete Personen bleiben angemeldet. Bereits verschickte Links bleiben gültig, weil `APP_KEY` sich nie ändert. |

**Ausfallzeit.** Während des Neustarts ist die Seite kurz nicht erreichbar, erst mit 502, dann gar nicht, bis `web` wieder
läuft. Gemessen waren es 7 und 14 Sekunden bei Migrationen unter 0,1 Sekunden. Eine Migration, die eine große Tabelle
umschreibt, verlängert das. `php-fpm` hat drei Minuten, bevor es als ungesund gilt. Eine Wartungsseite gibt es nicht.

**Suche.** Nur wenn der Changelog ausdrücklich sagt, dass sich die Suchdokumente geändert haben, führst Du einmal
`docker compose exec php-fpm php artisan search:reindex` aus.

> [!NOTE]
> `docker compose pull` aktualisiert auch PostgreSQL, Redis und Meilisearch, aber nur innerhalb der Version, die
> `docker-compose.yaml` festlegt (`postgres:16-alpine`, `redis:8-alpine`, `getmeili/meilisearch:v1.11`). Neue
> Hauptversionen mit neuem Datenformat kommen nie so herein. Ändert eine Version einen dieser Pins, steht das als
> **Migration required:** im Changelog, mit den Schritten.

## Wenn etwas schiefgeht

Suche zuerst, welcher Schritt hakt.

**Ein Dienst bleibt `starting` oder startet ständig neu.** Schau in das Log von `php-fpm`:

```sh server
docker compose logs --tail 100 php-fpm
```

- Wenn eine Migration läuft, zeigt das Log das, und `php-fpm` bleibt bis zu drei Minuten im Zustand `starting`. Das ist
  normal, warte ab.
- Ist eine Migration **fehlgeschlagen**, startet `php-fpm` immer wieder neu, die Seite antwortet mit 502, und das Log nennt
  die Migration mit `FAIL` und dem Datenbankfehler. PostgreSQL macht eine fehlgeschlagene Migration vollständig rückgängig,
  die Datenbank bleibt also so, wie die vorige Version sie hinterlassen hat. Gehe dann zurück (siehe unten) und melde
  den Fehler. Führe Migrationen nie von Hand aus.
- Steht dort „APP_KEY is not set“, fehlt der Schlüssel in der `.env`.

**Der Start klappt, aber der Health-Check ist rot.** `nusszopf:health` nennt den fehlgeschlagenen Check und den Grund. Die
häufigsten Fälle mit ihren Lösungen stehen unter [Fehlerbehebung](fehlerbehebung.md).

**Docker meldet eine fehlende Variable** (`Set NUSSZOPF_VERSION …`, `Set MAIL_FROM_ADDRESS …`). Compose liest Deine `.env` und
verlangt diese Zeile. Trage sie ein und starte erneut.

## Zurückgehen (Rollback)

> [!WARNING]
> Rollback heißt: das Backup von vor dem Deployment wiederherstellen. Führe **nie** `php artisan migrate:rollback` aus. Die
> `down()`-Schritte sind ungetestet, und manche löschen Tabellen samt Inhalt, etwa Newsletter-Anmeldungen und Aufrufzähler.

Alles, was nach dem Backup geschrieben wurde, geht dabei verloren, weil die Datenbank auf den Zeitpunkt des Backups zurückkehrt.

1. Stoppe den Stack, **ohne** `-v`, denn `-v` würde die Volumes löschen:

   ```sh server
   docker compose down
   ```

2. Stelle das Backup von Schritt 1 des Deployments mit den Befehlen aus
   [Wiederherstellen](backup.md#wiederherstellen) wieder her. Sie bringen auch die `.env` und `docker-compose.yaml`
   der alten Version zurück und starten den Stack.

Dieser Weg ist an einer Installation mit Daten geprüft, deren Schema vier Migrationen älter war: Die Datenbank hatte das alte
Schema wieder, genau die gesicherten Zeilen und keine, die nach dem Upgrade entstanden waren; die alte Version lief, und ein
erneutes Upgrade danach funktionierte.

**Die alte Version ohne Wiederherstellung starten.** Das behält die Daten, die seit dem Upgrade entstanden sind, ist aber nur
sicher, wenn die Release-Notes der Version, die Du verlässt, keine Migration nennen oder ausdrücklich sagen, dass die
Vorgängerversion auf dem neuen Schema läuft. Dann legst Du `docker-compose.yaml.previous` und `.env.previous` zurück und
startest neu. Die neueren Migrationen bleiben dabei angewendet; die alte Version kennt sie nicht und ignoriert die neuen
Spalten. Als Versprechen gilt das nicht, sondern nur dort, wo die Release-Notes es erlauben.

```sh server
cp docker-compose.yaml.previous docker-compose.yaml
cp .env.previous .env
docker compose up -d
```

## Eigene Änderungen an der Compose-Datei

`install.sh --upgrade` ersetzt `docker-compose.yaml` bei jedem Deployment, weil eine Version die Datei ändern darf (ein
neues Volume, eine neue Pflichteinstellung). Eigene Anpassungen gehören deshalb in eine `compose.override.yaml`. Compose
liest sie zusätzlich, und kein Upgrade ersetzt sie. Wenn Du die Datei früher direkt geändert hast, findest Du Deine
Änderungen in `docker-compose.yaml.previous`.

## Und für die Betreiber:innen von nusszopf.org?

Die Demo läuft mit derselben Compose-Datei und demselben Ablauf. Dazu kommt `NUSSZOPF_DEMO=true` in der `.env`, siehe
[Demo-Modus](demo.md). Einen eigenen Deployment-Pfad gibt es nicht.

## Wie eine Version entsteht

Das ist die Seite der Maintainer: [Versionen und Veröffentlichung](releases.md).
