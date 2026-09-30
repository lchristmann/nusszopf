---
titel: Installation
beschreibung: Vom leeren Verzeichnis zur laufenden Instanz — mit Docker, einer Domain und einem Mailanbieter.
gruppe: betreiben
reihenfolge: 1
---

# Installation

Der Nusszopf läuft als Docker-Compose-Stack aus fertigen Images. Auf dem Server wird nichts kompiliert und weder PHP noch
Node installiert. Ein Skript lädt die Dateien des gewünschten Releases herunter und erzeugt die Geheimnisse; danach
trägst Du nur noch Deine Absenderadresse und den Mailversand ein.

## Was Du brauchst

- Einen Linux-Server mit **Docker Engine und dem Compose-Plugin** (`docker compose`, nicht das alte `docker-compose`).
  Die Installation ist auf einem frischen Ubuntu 24.04 mit Docker Engine 29 geprüft; die Images gibt es für amd64 und arm64.
- `curl` und `openssl` auf dem Server.
- Eine **Domain**, die auf den Server zeigt, wenn die Instanz öffentlich sein soll.
- Einen **Mailanbieter**, der für Deine Absenderadresse senden darf ([Konfiguration](konfiguration.md#mailversand)).
  Ohne Mailversand läuft der Nusszopf, verschickt aber keine Mails, also auch keine Bestätigungen und keine
  Passwort-Zurücksetzen-Links.
- Einen **Reverse Proxy**, der TLS übernimmt (Caddy, Traefik oder Nginx Proxy Manager). Der Stack bringt bewusst keinen mit.

## Schritt für Schritt

**1. Installieren.** Lege ein Verzeichnis an und führe das Installationsskript mit der öffentlichen Adresse aus. Das
Skript lädt `docker-compose.yaml` und die Vorlage für die Einstellungen, erzeugt `APP_KEY` und die Passwörter für
Datenbank und Suche und schreibt alles in eine `.env` (nur für Dich lesbar). Es startet nichts und überschreibt keine
vorhandene `.env`.

```sh server
mkdir /opt/nusszopf && cd /opt/nusszopf
curl -fsSLO https://github.com/lchristmann/nusszopf/releases/latest/download/install.sh
sh install.sh https://nusszopf.example.org
```

`releases/latest` ist die neueste stabile Version. Eine bestimmte Version nennst Du als zweites Argument, zum Beispiel
`sh install.sh https://nusszopf.example.org 1.0.0`. Dann lädst Du auch das Skript aus dieser Version
(`…/releases/download/1.0.0/install.sh`).

**2. `.env` bearbeiten.** Zwei Angaben kann nur Du machen:

```env
MAIL_FROM_ADDRESS=hallo@nusszopf.example.org
MAIL_MAILER=resend
RESEND_API_KEY=re_…
```

`MAIL_FROM_ADDRESS` ist Pflicht; Compose startet ohne sie nicht. Sie muss auf einer Domain liegen, die Dein Mailanbieter
für Dich verifiziert hat. Läuft ein Reverse Proxy auf demselben Server, setze außerdem `APP_BIND=127.0.0.1`, damit nur er
den Port erreicht. Alles Weitere steht unter [Konfiguration](konfiguration.md).

**3. Starten.**

```sh server
docker compose up -d
```

Beim ersten Start werden die Images geladen, PostgreSQL, Redis und Meilisearch abgewartet, die Datenbank angelegt und die
Sucheinstellungen gesetzt. Das dauert je nach Verbindung ein paar Minuten.

**4. Prüfen.** Warte, bis alle Dienste `healthy` sind, und lass dann den Health-Check laufen:

```sh server
docker compose ps
docker compose exec php-fpm php artisan nusszopf:health
```

`queue-worker` und `scheduler` melden sich mit einem Herzschlag pro Minute und sind deshalb bis zu ein paar Minuten
nach dem Start noch nicht gesund. Der Health-Check zeigt sie bis dahin als `FAILED` mit „no heartbeat yet“. Das ist kein
Fehler, sondern der Check bei der Arbeit.

**5. Rechtstexte eintragen.** Impressum, Rechtliches und Datenschutz zeigt der Nusszopf nur, wenn Du sie hinterlegst
([Konfiguration](konfiguration.md#rechtstexte)). Bis dahin sagen die drei Seiten, dass der Text noch fehlt.

**6. Konto anlegen.** Öffne die Adresse Deiner Instanz und registriere Dich ganz normal. Es gibt keinen Admin-Schritt und
keinen Artisan-Befehl dafür: Alle Konten sind gleichberechtigt.

> [!TIP]
> Sichere gleich nach der Installation Deine `.env`. Sie enthält den `APP_KEY`; ohne ihn funktionieren bereits
> verschickte Links (Bestätigung, Passwort zurücksetzen) nicht mehr. Wie ein vollständiges Backup aussieht, steht unter
> [Backup und Wiederherstellung](backup.md).

## Reverse Proxy und TLS

Der Stack veröffentlicht genau einen Port: `web` auf `APP_PORT` (Standard `8080`). Davor sitzt Dein Reverse Proxy und
reicht Deine Domain an `http://<server>:8080` weiter. Mit Caddy genügt:

```text
nusszopf.example.org {
    reverse_proxy 127.0.0.1:8080
}
```

Damit die Anwendung dem Proxy glaubt, welche Adresse und welches Protokoll der Besucher benutzt hat, gelten in der `.env`
drei Einstellungen, die die Vorlage schon richtig setzt:

- `APP_URL` beginnt mit `https://` und ist **genau** die öffentliche Adresse. Jeder Link in Seiten und Mails beginnt damit.
- `TRUSTED_PROXIES` nennt, welchen Proxys geglaubt wird. Voreingestellt sind Loopback und die privaten Netze; das deckt
  einen Proxy auf demselben Server oder im lokalen Netz ab.
- `SESSION_SECURE_COOKIE=true` schickt Cookies nur über https.

> [!WARNING]
> Setze `TRUSTED_PROXIES` nie auf `*`, solange der Port aus dem Internet erreichbar ist. Dann könnte jeder Besucher seine
> eigene Adresse in `X-Forwarded-For` schreiben und damit die Begrenzungen für Login, Registrierung und die öffentlichen
> Formulare umgehen. Steht der Proxy unter einer öffentlichen Adresse, trage genau diese Adresse in die Liste ein.

HSTS überlässt der Nusszopf Deinem Proxy, der weiß, ob die Seite dauerhaft nur über https laufen soll. Sicherheits-Header
und eine Content-Security-Policy sendet die Anwendung selbst.

## Was auf dem Server liegt

| Ort | Inhalt | Im Backup? |
|---|---|---|
| `/opt/nusszopf/.env` | Alle Einstellungen und Geheimnisse | ja |
| `/opt/nusszopf/docker-compose.yaml` | Der Stack, passend zur installierten Version | ja |
| `/opt/nusszopf/legal/` | Deine Rechtstexte | ja |
| Volume `postgres-data` | Die Datenbank | ja, als Dump |
| Volume `laravel-storage` | Hochgeladene Avatare | ja |
| Volume `meilisearch-data` | Der Suchindex, abgeleitet aus der Datenbank | nein, wird neu aufgebaut |
| Volume `redis-data` | Sitzungen, Warteschlange, Cache | nein |

## Ohne `install.sh`

Du kannst die Dateien auch von Hand holen: `docker-compose.yaml` und `env.production.example` aus dem Release laden,
letztere als `.env` speichern und alle mit `REQUIRED` markierten Zeilen ausfüllen. Den Schlüssel erzeugt dieser Befehl:

```sh server
docker compose run --rm --no-deps --entrypoint php php-fpm artisan key:generate --show
```

Dafür muss die `.env` vorher `NUSSZOPF_VERSION` und die Passwörter für Datenbank und Suche enthalten, denn Compose liest
sie beim Start.

## Weiter

Als Nächstes lohnt sich die [Konfiguration](konfiguration.md), besonders Mailversand und Rechtstexte. Einen
Backup-Plan solltest Du vor dem ersten echten Nutzer haben: [Backup und Wiederherstellung](backup.md).
