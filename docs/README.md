# Dokumentation

Die Dokumentation des Nusszopf hat zwei Teile.

## Das Handbuch (Deutsch)

**[`handbuch/`](handbuch/README.md)** ist die Dokumentation für alle, die den Nusszopf betreiben, weiterentwickeln oder verstehen
wollen. Es beschreibt den heutigen Stand, ist auf Deutsch und wird auf der Projektseite veröffentlicht:
<https://lchristmann.github.io/nusszopf/handbuch/>.

| Wenn Du … | dann |
|---|---|
| eine Instanz betreiben willst | [Installation](handbuch/installation.md), [Konfiguration](handbuch/konfiguration.md), [Deployment](handbuch/deployment.md) |
| im Betrieb etwas suchst | [Betrieb](handbuch/betrieb.md), [Backup](handbuch/backup.md), [Fehlerbehebung](handbuch/fehlerbehebung.md) |
| mitentwickeln willst | [Lokale Entwicklung](handbuch/entwicklung.md), [Architektur](handbuch/architektur.md), [Tests](handbuch/tests.md) |
| wissen willst, wo was steht | [Dokumentation pflegen](handbuch/dokumentation.md) |

## Die Spezifikation (Englisch)

Alle übrigen Ordner hier sind die Spezifikation des Rewrites: die Belege für das Verhalten des historischen Nusszopf, die
Entscheidungen und die Prüfberichte. Sie sind englisch, weil sie die englischsprachigen Originalquellen zitieren, und sie sind die Quelle
der Wahrheit für das Produktverhalten.

- `architecture/` — Zuordnung der historischen Bausteine zur neuen Architektur
- `authentication/` — Verhalten der Anmeldung
- `design/` — die genaue historische Oberfläche (`screens.md` als Beleg, `screen-specs.md` als Checkliste je Seite)
- `domain/` — Domänenmodell und Geschäftsregeln
- `email/` — Verhalten der Mails
- `journeys/` — Abnahmeabläufe
- `legal/` — Herkunft und Lizenzen von Code, Schriften und Bildern (`provenance.md`)
- `release/` — Versionierung, Release-Prozess und die Prüfberichte unter `release/parity/`
- `rewrite/` — Vorgehen und Entscheidungen: `decisions-register.md`, `bugs.md`, `intentional-changes.md`, `open-questions.md`,
  `architecture-decisions.md`, `master-roadmap.md` und eine Seite je umgesetzter Etappe
- `search/` — Verhalten der Suche
- `security/` — Sicherheitsanforderungen, darin `authorization-matrix.md`
- `testing/` — Teststrategie, Barrierefreiheit, visuelle Regression
- `deployment/` und `development/` — Verweise auf das Handbuch (die Betriebs- und Entwicklungsdokumentation zog dorthin um);
  `deployment/legal-examples/` enthält die Rechtstexte des ursprünglichen Betreibers als Beispiele

## Wo Du anfängst, wenn Du am Produkt arbeitest

1. `CLAUDE.md` und `.claude/rules/` — die Regeln, die jede Änderung einhalten muss.
2. `rewrite/source-map.md` — welche Revision der historischen Repositories jede Seite abbildet.
3. `rewrite/decisions-register.md` — was entschieden ist und was noch eine menschliche Entscheidung braucht.
4. `rewrite/bugs.md` — jeder eingeordnete historische Fehler.
5. Der passende Themenordner oben.

Die archivierten Repositories des ursprünglichen Nusszopf sind die Referenz für das Produkt; siehe [Konventionen](handbuch/konventionen.md#wo-die-wahrheit-liegt).
