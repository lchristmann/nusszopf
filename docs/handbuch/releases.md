---
titel: Versionen und Veröffentlichung
beschreibung: Wie Versionen nummeriert und veröffentlicht werden, was im Changelog stehen muss und was vor einem Tag geprüft wird.
gruppe: hintergrund
reihenfolge: 1
---

# Versionen und Veröffentlichung

Diese Seite richtet sich an die Maintainer. Betreiber:innen finden das Einspielen einer Version unter
[Deployment](deployment.md).

## Versionsnummern

Der Nusszopf folgt [Semantic Versioning](https://semver.org/lang/de/): `MAJOR.MINOR.PATCH`.

- **MAJOR**: Betreiber:innen müssen mehr tun als „Images laden, starten“ (ein Handgriff, ein entfernter Schalter, eine
  nicht umkehrbare Datenmigration).
- **MINOR**: neue Funktionen, abwärtskompatibel.
- **PATCH**: Fehler- und Sicherheitskorrekturen.

Tags haben **kein `v`** davor: `1.2.0`, nicht `v1.2.0`. Vorab-Versionen heißen `X.Y.Z-rc.N`; sie werden als GitHub-Pre-Release
veröffentlicht und bewegen `latest` nicht. Die erste stabile Version ist `1.0.0`.

Die Version steckt im Git-Tag und sonst nirgends. Der Build bäckt sie als `NUSSZOPF_VERSION` in die Images und in ein Label
(`org.opencontainers.image.version`). `nusszopf:health`, `php artisan about` und `/health` (mit Token) zeigen sie ohne
Datenbankzugriff. Ein aus einer Arbeitskopie gebautes Image meldet `dev`. Es gibt keine handgepflegte Versionsdatei.

## Regeln, damit Upgrades gelingen

- Migrationen sind innerhalb einer Hauptversion **additiv und vorwärts** gerichtet. Nur ein MAJOR darf eine Migration verlangen,
  die Betreiber:innen etwas tun lässt.
- Image-Tags sind unveränderlich: Ein Tag wird nie erneut unter demselben Namen hochgeladen.
- Ein Handgriff über die üblichen drei Befehle hinaus gehört als **Migration required:** in den Changelog. Das sind zum Beispiel
  eine neue Pflichteinstellung ohne Standardwert, ein geänderter Standard, den eine bestehende `.env` nicht übernimmt, ein anderer
  Pin von PostgreSQL, Redis oder Meilisearch, ein nötiger `search:reindex` oder eine Version, deren Vorgängerin auf dem neuen Schema
  nicht läuft. Ein geändertes Meilisearch-Pin bedeutet, das Volume `meilisearch-data` zu entfernen und neu zu indexieren; das ist
  aus der Dokumentation von Meilisearch abgeleitet, nicht erprobt.
- Ein bloß neue, optionale Einstellung oder eine geänderte Compose-Datei braucht keinen Marker: `install.sh --upgrade` bringt die
  Datei mit und listet fehlende Einstellungen selbst auf.
- Ein **Breaking:**-Eintrag nennt, was sich ändert, wen es betrifft, die nötigen Befehle zum Kopieren, Folgen für Datenbank und
  Konfiguration und die Besonderheiten des Rollbacks.

## Der Changelog

`CHANGELOG.md` folgt [Keep a Changelog](https://keepachangelog.com/de/): `Added`, `Changed`, `Fixed`, `Security`, `Deprecated`,
`Removed`, dazu oben ein Abschnitt `Unreleased`. Beim Release wird er zum Abschnitt mit Version und Datum, und ein neuer, leerer
`Unreleased` kommt darüber. Der Abschnitt einer Version ist zugleich der Text der GitHub-Release-Notes: Der Workflow zieht ihn heraus
und **weigert sich zu veröffentlichen**, wenn er fehlt.

Wer den Changelog pflegt (Autor:in des Pull Requests oder Maintainer beim Release), ist noch offen; bisher tun es die Maintainer.

## Eine Version veröffentlichen

1. **Changelog vorbereiten.** Verschiebe `Unreleased` unter `## [X.Y.Z] - Datum`.
2. **Prüfen** (Details in [Tests und Qualität](tests.md#prüfungen-für-releases)): das Upgrade von der letzten Version, Backup mit
   Restore und Rollback, Suchindex-Wiederherstellung, Queue und Scheduler, und mit einem echten Postfach der Mailversand.
3. **Die CI des genauen Commits ansehen.** Ein CI-Lauf, der nie gestartet ist oder rot wurde, ist im Terminal unsichtbar. Der
   Release-Workflow führt dieselbe CI zwar noch einmal als Schranke aus, aber bemerke es vorher.
4. **Mergen und taggen:**

   ```sh dev
   git tag -a X.Y.Z -m X.Y.Z && git push origin X.Y.Z
   ```

Der Tag startet `.github/workflows/release.yml`. Er lässt die ganze CI als Schranke laufen (Pint, Larastan, Pest, Playwright auf drei
Browsern, Produktions-Stack). Er baut beide Images für `linux/amd64` und `linux/arm64` (rund 40 Minuten) und lädt sie nach
`ghcr.io/lchristmann/nusszopf-php-fpm` und `…/nusszopf-web`, getaggt mit `X.Y.Z` und `latest` (Vorab-Versionen nur mit der Version).
Zuletzt legt er das GitHub-Release mit den Notes aus dem Changelog an und hängt `docker-compose.yaml`, `env.production.example` und
`install.sh` an.

Die Vorlage heißt als Anhang `env.production.example` ohne Punkt, denn GitHub benennt Dateien mit führendem Punkt um. `install.sh`
speichert sie auf dem Server unter dem Namen mit Punkt. `tests/Feature/Release/ReleaseAssetsTest.php` hält die Downloads des
Installers und die Anhänge des Workflows im Gleichschritt.

## Nach dem Tag

Prüfe, dass sich beide Pakete ohne Anmeldung ziehen lassen; von einem Rechner, der nicht bei GHCR angemeldet ist:

```sh dev
docker manifest inspect ghcr.io/lchristmann/nusszopf-web:X.Y.Z
```

Dann installiert `sh scripts/release-check.sh X.Y.Z` die veröffentlichte Version so, wie es Betreiber:innen tun. Ein Tag
`verify/X.Y.Z`, der auf denselben Commit zeigt, startet `release-verify.yml`, das die Drills auf den gezogenen Images
nativ auf amd64 und arm64 wiederholt:

```sh dev
git tag verify/X.Y.Z X.Y.Z^{} && git push origin verify/X.Y.Z
```

## Was noch offen ist

Zum Zeitpunkt von `1.0.0` stehen diese Prüfungen aus und sind unter [`docs/release/parity/`](../release/parity/README.md)
vermerkt: eine Installation durch eine zweite Person auf einem echten Server mit ACME-Zertifikat, die Darstellung der Mails in
Gmail, Outlook und Apple Mail und ein Durchgang auf echten Telefonen.

Die englischen Bestandsseiten zum Thema liegen unter [`docs/release/`](../release/README.md).
