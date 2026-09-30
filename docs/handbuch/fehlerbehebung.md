---
titel: Fehlerbehebung
beschreibung: Symptome, die im Betrieb tatsächlich aufgetreten sind, mit Ursache und Lösung.
gruppe: betreiben
reihenfolge: 6
---

# Fehlerbehebung

Beginne bei der Diagnose: Sie sagt Dir meist schon, wo es klemmt. Die Tabellen darunter beschreiben Probleme, die beim
Testen des Betriebs aufgetreten sind oder sich direkt aus der Konfiguration ergeben.

```sh server
docker compose ps                                        # welcher Dienst ist nicht "healthy"?
docker compose exec php-fpm php artisan nusszopf:health  # welcher Check schlägt fehl, und warum?
docker compose logs --tail 100 php-fpm queue-worker      # was sagt das Log?
```

Für Probleme in der lokalen Entwicklung gibt es eine eigene Tabelle unter
[Lokale Entwicklung](entwicklung.md#wenn-etwas-hakt).

## Start und Konfiguration

| Symptom | Ursache und Lösung |
|---|---|
| `docker compose up` meldet eine fehlende Variable (`Set NUSSZOPF_VERSION…`, `Set DB_PASSWORD…`, `Set MAIL_FROM_ADDRESS…`) | Compose liest Deine `.env` und verlangt diese Zeile. Trage sie ein. |
| `php-fpm` beendet sich sofort mit „APP_KEY is not set“ | Erzeuge einen Schlüssel (siehe [Betrieb](betrieb.md#einmalige-artisan-befehle)) und trage ihn als `APP_KEY` ein. |
| Eine geänderte `.env` wirkt nicht | Ein Container behält die Umgebung, mit der er erstellt wurde, und die Konfiguration ist beim Start zwischengespeichert. Führe `docker compose up -d` aus. `restart` reicht nicht. |
| `php-fpm` bleibt nach einem Deployment minutenlang `starting` | Eine Migration läuft (`docker compose logs -f php-fpm`). Bis zu drei Minuten sind normal. Scheitert sie, startet `php-fpm` neu und die Seite antwortet mit 502: gehe zurück, siehe [Deployment](deployment.md#zurückgehen-rollback). |
| `queue-worker` oder `scheduler` sind kurz nach dem Start `unhealthy` | Sie brauchen ihren ersten Herzschlag, bis zu einer Minute nach `php-fpm`. Bleibt es dabei: `docker compose logs scheduler queue-worker`. |

## Gesundheit, Warteschlange und Suche

| Symptom | Ursache und Lösung |
|---|---|
| `/health` meldet `degraded`, `failed_jobs` schlägt fehl | Aufträge haben alle Versuche aufgebraucht. `queue:failed` nennt sie mit Grund. Ursache beheben, dann `queue:retry all` (bei Mails, sie stehen nur dort) oder nach einem `search:reindex` `queue:flush`. Die Tabelle muss leer sein, damit der Check besteht. |
| `queue` und `scheduler` schlagen beide fehl | Fange beim Scheduler an. Er stößt den Herzschlag an, den der Queue-Check liest: `docker compose ps`, dann `docker compose up -d scheduler`. |
| Die Suche zeigt nichts oder Veraltetes | `nusszopf:health` (ist `search` ok?), `queue:failed`, dann `search:reindex`. Startet Meilisearch ständig neu: [Suchindex](betrieb.md#suchindex). |
| Mails kommen nicht an, `failed_jobs` nennt eine Mail | `queue:failed` gibt den Grund des Anbieters: nicht verifizierte Absenderdomain, falscher Schlüssel oder Login. Siehe [Mail im Betrieb](betrieb.md#mail-im-betrieb). |

## Proxy, Links und Adressen

| Symptom | Ursache und Lösung |
|---|---|
| Links oder Weiterleitungen verwenden `http://`, oder der Login dreht sich im Kreis | Prüfe `TRUSTED_PROXIES`, `APP_URL=https://…` und `SESSION_SECURE_COOKIE` in der `.env`, dann `docker compose up -d`. |
| Ein Link aus einer Mail (Bestätigung, „Das bin ich!“) antwortet mit 403, oder Stile und Skripte laden nicht | Jeder Link beginnt mit `APP_URL`; sie muss die exakte öffentliche Adresse sein. Ein Proxy außerhalb der privaten Netze muss in `TRUSTED_PROXIES` stehen, sonst sieht die Anwendung `http`, wo der signierte Link `https` sagt. |
| „Zu viele Versuche. Bitte warte kurz.“ bei allen Besucher:innen | Die Anwendung sieht alle unter der Adresse des Proxys: Er ist nicht vertraut (`TRUSTED_PROXIES`), also zählen die Begrenzungen alle zusammen. |
| Nach einem Deployment fehlen Stile oder die Oberfläche ist veraltet | `web` und `php-fpm` einer Version tragen immer passende Assets. Prüfe mit `docker compose images`, dass beide dieselbe `NUSSZOPF_VERSION` haben, und führe `docker compose pull` aus. |

## Backup und Wiederherstellung

| Symptom | Ursache und Lösung |
|---|---|
| Ein Backup-Ordner endet auf `.incomplete` | Das Backup ist gescheitert. Das Log (`/var/log/nusszopf-backup.log`) nennt den Grund. Nur Ordner ohne diese Endung sind vollständig. |
| Beim Zurückspielen scheitert eine Zeile | Behebe die Ursache und führe alle Zeilen von vorn aus, jede lässt sich wiederholen. |

## Wenn nichts davon passt

Sammle die Ausgabe der drei Befehle oben und melde das Problem auf [GitHub](https://github.com/lchristmann/nusszopf/issues),
ohne Passwörter und ohne den Inhalt Deiner `.env`. Sicherheitsprobleme meldest Du nicht öffentlich, sondern wie in
[SECURITY.md](../../SECURITY.md) beschrieben.
