# Zum Nusszopf beitragen

Danke, dass Du mithelfen willst. Der Nusszopf ist eine **getreue Neuimplementierung** des historischen Nusszopf
(`github.com/nusszopf/web-nusszopf`, `be-nusszopf`, `emails-nusszopf`), kein Redesign. Vieles, was nach einer offenen
Gestaltungsfrage aussieht, ist durch das Original oder eine dokumentierte Entscheidung schon beantwortet. Ein wenig Lesen vorab
erspart allen eine Runde.

Mit Deiner Teilnahme stimmst Du dem [Verhaltenskodex](CODE_OF_CONDUCT.md) zu. Ein Sicherheitsproblem gehört in
[`SECURITY.md`](SECURITY.md), nie in ein öffentliches Issue.

## Bevor Du anfängst

1. Lies [`docs/rewrite/decisions-register.md`](docs/rewrite/decisions-register.md) (was entschieden ist und was noch eine
   menschliche Entscheidung braucht) und [`docs/rewrite/bugs.md`](docs/rewrite/bugs.md) (jeder eingeordnete historische Fehler).
   Entschiedenes wird nicht neu verhandelt, Offenes nicht selbst entschieden: Öffne dafür ein Issue.
2. Lies die Fachseite zu Deinem Bereich (`docs/domain/`, `docs/design/`, `docs/security/` …; der Index ist
   [`docs/README.md`](docs/README.md)). Wo **Unknown** steht, steht die Antwort im ursprünglichen Nusszopf (siehe
   [Konventionen](docs/handbuch/konventionen.md#wo-die-wahrheit-liegt)) oder in
   `docs/rewrite/open-questions.md`; fülle die Lücke nicht mit einer Vermutung.
3. Öffne für alles Größere als eine Korrektur oder Dokumentationsänderung zuerst ein Issue, damit Du nichts baust, das nicht
   übernommen werden kann.

## Was passt und was zuerst freigegeben werden muss

- **Ohne Umstände willkommen:** Korrekturen an Dokumentation, Tests, Werkzeugen und CI, an Installation, Upgrade und Backup, und
  Stellen, an denen der Nusszopf vom historischen Produkt abweicht.
- **Zuerst mit den Maintainern klären:** jede Änderung an Verhalten oder Design, auch die Behebung eines Fehlers des
  historischen Produkts. Die Regeln stehen in `CLAUDE.md` („Product fidelity rules“, „Bug classification workflow“): Der Fehler
  bekommt **vor** der Korrektur einen `BUG-NNN`-Eintrag in `docs/rewrite/bugs.md` und einen Eintrag in
  `docs/rewrite/intentional-changes.md`, und die Korrektur kommt mit einem Regressionstest. Das Design ist verbindlich
  (`docs/design/`); Tailwind ist ein Mittel, es nachzubauen, kein Grund, es zu ändern.
- **Nicht angenommen:** SaaS-Konzepte (Mandanten, Abrechnung, Messung, Pflicht-Fremddienste) und alles, was das Selbst-Hosting
  erschwert.

## Entwicklungsumgebung

Alles läuft in Docker; auf Deinem Rechner brauchst Du nur Docker, Docker Compose und Git. Folge
[Lokale Entwicklung](docs/handbuch/entwicklung.md); dort steht auch die Tabelle mit den Stolpersteinen.

## Bevor Du einen Pull Request öffnest

Lass laufen, was CI auch laufen lässt, in dieser Reihenfolge ([Tests und Qualität](docs/handbuch/tests.md)):

```shell
docker compose -f compose.dev.yaml exec workspace composer lint:check
docker compose -f compose.dev.yaml exec workspace composer larastan
docker compose -f compose.dev.yaml exec workspace composer test
docker compose -f compose.dev.yaml exec playwright npx playwright test
```

Der letzte Befehl sind die Browser-Tests; lass die Projekte laufen, die Deine Änderung berühren kann. `composer pint` formatiert den
Code. Eine Änderung ist fertig, wenn ihre Tests existieren (ein Happy Path allein genügt nicht), die betroffene Seite unter `docs/` **in
derselben Änderung** aktualisiert ist und alle Zustände einer Seite (Laden, leer, Fehler, Erfolg) abgedeckt sind.

## Pull Requests

- Forke das Repository, branche von `main` und öffne den Pull Request gegen `main`. Die Vorlage nennt, was geprüft wird.
- Ein Pull Request behandelt ein Anliegen. Eine Fehlerkorrektur ist kein Anlass, die Umgebung umzugestalten.
- Commit-Nachrichten folgen dem Stil der Historie, `typ(bereich): zusammenfassung`, etwa `fix(queue): …` oder `docs: …`, im
  Imperativ und mit dem Grund im Text, wenn er nicht offensichtlich ist.
- Ändere weder `CHANGELOG.md` noch Versionsnummern: Der Release-Prozess ([Versionen und Veröffentlichung](docs/handbuch/releases.md))
  gehört den Maintainern. Beschreibe im Pull Request, was Betreiber:innen anders machen müssen.
- Committe keine Geheimnisse, `.env`-Dateien oder Personendaten, und füge keine Schriften, Bilder oder Codeteile hinzu, deren
  Lizenz Du nicht geprüft hast: [`docs/legal/provenance.md`](docs/legal/provenance.md) sagt, was woher stammt, `NOTICE` nennt
  die Fremdlizenzen.

## Lizenz Deiner Beiträge

Der Nusszopf steht unter der **GNU General Public License v3.0 oder später** (`LICENSE`). Mit einem Beitrag lizenzierst Du ihn unter
denselben Bedingungen. Eine Contributor-Vereinbarung gibt es nicht.

## KI-Assistenten

Wenn Du einen KI-Assistenten benutzt, verweise ihn auf `CLAUDE.md` und `.claude/rules/`: Dort stehen die obigen Regeln in der Form,
die solche Werkzeuge lesen. Du bleibst verantwortlich für das, was Du einreichst; alle Prüfungen oben gelten dafür in vollem Umfang.
