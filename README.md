# Nusszopf

> Die quelloffene, selbst hostbare Community-Plattform für Ideen und Projekte.

[![CI](https://github.com/lchristmann/nusszopf/actions/workflows/ci.yml/badge.svg)](https://github.com/lchristmann/nusszopf/actions/workflows/ci.yml)
[![Security](https://github.com/lchristmann/nusszopf/actions/workflows/security.yml/badge.svg)](https://github.com/lchristmann/nusszopf/actions/workflows/security.yml)
[![License: GPL v3 or later](https://img.shields.io/badge/License-GPL--3.0--or--later-blue.svg)](LICENSE)

![PHP](https://img.shields.io/badge/PHP-8.5-777BB4?logo=php&logoColor=white)
![Laravel](https://img.shields.io/badge/Laravel-13-FF2D20?logo=laravel&logoColor=white)
![PostgreSQL](https://img.shields.io/badge/PostgreSQL-16-4169E1?logo=postgresql&logoColor=white)
![Blade](https://img.shields.io/badge/Blade-Template-F05340?logo=laravel&logoColor=white)
![Livewire](https://img.shields.io/badge/Livewire-4-FB70A9?logo=livewire&logoColor=white)
![Tailwind CSS](https://img.shields.io/badge/Tailwind_CSS-4-06B6D4?logo=tailwindcss&logoColor=white)
![Redis](https://img.shields.io/badge/Redis-Cache_%26_Queue-FF4438?logo=redis&logoColor=white)
![Meilisearch](https://img.shields.io/badge/Meilisearch-1.11-FF5CAA?logo=meilisearch&logoColor=white)
![Pest](https://img.shields.io/badge/Pest-5-8A4182?logo=php&logoColor=white)
![Playwright](https://img.shields.io/badge/Playwright-E2E_Testing-2EAD33?logo=playwright&logoColor=white)
![Larastan](https://img.shields.io/badge/Larastan-Level_7-4F5B93?logo=php&logoColor=white)
![Laravel Pint](https://img.shields.io/badge/Laravel_Pint-Code_Style-FF2D20?logo=laravel&logoColor=white)
![Docker](https://img.shields.io/badge/Docker-Compose-2496ED?logo=docker&logoColor=white)
![GitHub Actions](https://img.shields.io/badge/GitHub_Actions-CI/CD-2088FF?logo=githubactions&logoColor=white)

Der Nusszopf ist eine Community-Plattform, auf der Menschen Projekte einstellen und dazuschreiben, was ihnen noch fehlt:
Mitstreiter:innen, Räume, Materialien, Geld oder etwas anderes. Er ist freie Software und lässt sich von jeder und jedem selbst
betreiben, für eine Community, eine Schule, eine Firma oder einen Verein.

Diese Fassung ist die Neuimplementierung des ursprünglichen Nusszopf mit moderner Technik. Funktionen, Abläufe und Aussehen
sind bewusst dieselben geblieben.

**[Demo ausprobieren](https://nusszopf.org)** · **[Handbuch](https://lchristmann.github.io/nusszopf/handbuch/)** · **[Projektseite](https://lchristmann.github.io/nusszopf/)**

## Selbst hosten

Du brauchst einen Server mit Docker und eine Domain, sonst nichts:

<!-- quickstart:start -->
<!-- Transcluded verbatim into site/index.html at build time (site/vite.config.js) — edit only here. -->
```sh
mkdir /opt/nusszopf && cd /opt/nusszopf
curl -fsSLO https://github.com/lchristmann/nusszopf/releases/latest/download/install.sh
sh install.sh https://nusszopf.example.org
# .env bearbeiten: MAIL_FROM_ADDRESS (erforderlich) und den Mailversand: Resend (MAIL_MAILER=resend, RESEND_API_KEY),
# empfohlen, oder ein eigenes SMTP-Relay (MAIL_HOST, MAIL_PORT, MAIL_USERNAME, MAIL_PASSWORD)
docker compose up -d
```
<!-- quickstart:end -->

`releases/latest` ist die neueste stabile Version. Das Handbuch führt Schritt für Schritt durch alles Weitere:
[Installation](docs/handbuch/installation.md), [Konfiguration](docs/handbuch/konfiguration.md),
[Deployment](docs/handbuch/deployment.md), [Betrieb](docs/handbuch/betrieb.md) und
[Backup und Wiederherstellung](docs/handbuch/backup.md).

## Mitentwickeln

Die Entwicklung läuft komplett in Docker; auf Deinem Rechner brauchst Du nur Docker, Docker Compose und Git.
Der Einstieg ist [Lokale Entwicklung](docs/handbuch/entwicklung.md), danach [Architektur](docs/handbuch/architektur.md),
[Geschäftslogik](docs/handbuch/geschaeftslogik.md) und [Tests und Qualität](docs/handbuch/tests.md).

## Dokumentation

| Wenn Du … | dann lies |
|---|---|
| einen Nusszopf betreiben willst | [Installation](docs/handbuch/installation.md) → [Konfiguration](docs/handbuch/konfiguration.md) → [Deployment](docs/handbuch/deployment.md) |
| etwas kaputt ist | [Fehlerbehebung](docs/handbuch/fehlerbehebung.md) |
| mitentwickeln willst | [Lokale Entwicklung](docs/handbuch/entwicklung.md), [Konventionen](docs/handbuch/konventionen.md) |
| wissen willst, warum etwas so ist | die englische Spezifikation unter [`docs/`](docs/README.md), zuerst [`decisions-register.md`](docs/rewrite/decisions-register.md) und [`bugs.md`](docs/rewrite/bugs.md) |

Versionen, Änderungen und Docker-Images stehen auf der [Releases-Seite](https://github.com/lchristmann/nusszopf/releases) und
im [Changelog](CHANGELOG.md).

## Mitwirken, Sicherheit, Verhaltenskodex

Beiträge sind willkommen: siehe [`CONTRIBUTING.md`](CONTRIBUTING.md). Sicherheitslücken meldest Du bitte privat, wie in
[`SECURITY.md`](SECURITY.md) beschrieben. Alle Beteiligten befolgen den [Verhaltenskodex](CODE_OF_CONDUCT.md).

## Lizenz

Der Nusszopf ist freie Software unter der **GNU General Public License v3.0 oder später** (`GPL-3.0-or-later`), siehe
[`LICENSE`](LICENSE). Komponenten Dritter und ihre Lizenzen nennt [`NOTICE`](NOTICE), die Herkunft der Assets
[`docs/legal/provenance.md`](docs/legal/provenance.md).

Der Nusszopf ist eine Neuimplementierung des ursprünglichen Nusszopf (`web-nusszopf`, `be-nusszopf`, `emails-nusszopf`), der
ebenfalls unter der GPL v3.0 steht. Design, Texte und E-Mail-Vorlagen sind hier als abgeleitete Werke unter derselben Lizenz
übernommen; die ursprünglichen Autor:innen behalten ihr Urheberrecht an diesem Material.
