# Deployment und Selbst-Hosting — verschoben

Die Installations- und Betriebsdokumentation ist jetzt das deutschsprachige **[Handbuch](../handbuch/README.md)**. Diese Seite bleibt
bestehen, damit ältere Verweise (in Code-Kommentaren, Prüfberichten und bereits ausgelieferten Versionen) ankommen.

| Früherer Abschnitt | Jetzt |
|---|---|
| Design goal, The files, Services | [Überblick](../handbuch/ueberblick.md), [Architektur](../handbuch/architektur.md), [Betrieb](../handbuch/betrieb.md#die-dienste) |
| Why a dedicated nginx image, Avatar storage and serving | [Architektur](../handbuch/architektur.md#der-betrieb-als-bauplan), [Speicher und Medien](../handbuch/betrieb.md#speicher-und-medien) |
| Required configuration, Configuration reference | [Konfiguration](../handbuch/konfiguration.md#alle-einstellungen) |
| Location search, Google login | [Ortssuche](../handbuch/konfiguration.md#ortssuche-locationiq), [Google-Login](../handbuch/konfiguration.md#google-login) |
| Newsletter | [Newsletter-Anmeldungen](../handbuch/betrieb.md#newsletter-anmeldungen) |
| Sending mail, Your identity | [Mailversand](../handbuch/konfiguration.md#mailversand) |
| Legal pages | [Rechtstexte](../handbuch/konfiguration.md#rechtstexte) |
| Installation (operator path) | [Installation](../handbuch/installation.md) |
| Reverse proxy and TLS | [Reverse Proxy und TLS](../handbuch/installation.md#reverse-proxy-und-tls) |
| Persistent storage | [Was auf dem Server liegt](../handbuch/installation.md#was-auf-dem-server-liegt) |
| Health checks | [Gesundheit prüfen](../handbuch/betrieb.md#gesundheit-prüfen) |
| Backups, upgrades, recovery | [Backup](../handbuch/backup.md), [Deployment](../handbuch/deployment.md) |

Die frühere englische Fassung mit allen Prüfverweisen (P-7 bis P-16) steht in der Git-Historie:
`git show 9c8fc8b:docs/deployment/README.md`. Die Prüfberichte selbst liegen weiterhin unter [`release/parity/`](../release/parity/README.md).

Unverändert hier: [`legal-examples/`](legal-examples/README.md), die Rechtstexte des ursprünglichen Betreibers, als Beispiele.
