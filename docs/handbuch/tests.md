---
titel: Tests und Qualität
beschreibung: Was vor einem Pull Request laufen muss, welche Prüfungen es gibt und warum sie so aufgeteilt sind.
gruppe: entwickeln
reihenfolge: 4
---

# Tests und Qualität

Das Ziel: **CI fängt genau das, was Du lokal auch fangen kannst, mit denselben Befehlen.** Es gibt keine Prüfung, die nur in CI
läuft, und keine lokale Regel, die CI nicht durchsetzt.

## Vor jedem Pull Request

Diese drei Befehle laufen in der Reihenfolge, in der sie am schnellsten scheitern. Danach kommen die Browser-Tests, und zwar die
Projekte, die Deine Änderung berühren kann.

```sh dev
docker compose -f compose.dev.yaml exec workspace composer lint:check
docker compose -f compose.dev.yaml exec workspace composer larastan
docker compose -f compose.dev.yaml exec workspace composer test
docker compose -f compose.dev.yaml exec playwright npx playwright test
```

`composer pint` formatiert den Code, statt ihn nur zu prüfen. Der Stack muss laufen, und die vollständige Playwright-Suite
braucht zwei Einstellungen (unten, [Browser-Tests](#browser-tests)).

## Die Prüfungen

Jede Zeile ist ein Job in `.github/workflows/ci.yml`. Der Release-Workflow lässt dieselbe CI vorher als Schranke laufen.

| Prüfung | Werkzeug | Lokal | Was sie findet |
|---|---|---|---|
| Formatierung | Laravel Pint | `composer lint:check` | Abweichungen vom Stil |
| Statische Analyse | Larastan, Level 7 | `composer larastan` | Typfehler, unbekannte Methoden, unsichere Nullwerte |
| Unit- und Feature-Tests | Pest gegen echtes PostgreSQL | `composer test` | Geschäftsregeln, Validierung, Berechtigungen, Aufträge, Mails, Suche |
| Frontend-Build | Vite | `npm run build` | Die Assets bauen |
| Browser-Tests | Playwright: Chromium, Firefox, WebKit, emulierte Telefone und Tablet | `npx playwright test` | Echte Abläufe, Barrierefreiheit (axe), Tastatur, CSP-Verstöße, Seitengewicht |
| Visuelle Regression | Playwright `toHaveScreenshot` | siehe unten | Jede Pixelabweichung der 23 Seiten in drei Breiten |
| Produktions-Stack | `scripts/smoke-test.sh` | dasselbe | Die Images bauen, installieren sich, starten gesund und antworten |
| Browser-Tests auf den Produktions-Images | `scripts/prod-e2e.sh` | dasselbe | Die ganze Playwright-Suite gegen genau diese Images |
| Abhängigkeiten | `composer audit`, `npm audit` | `composer audit --locked`, `npm audit --omit=dev` | Bekannte Sicherheitslücken. Der Workflow `Security` läuft wöchentlich, getrennt, weil eine neue Lücke ohne Änderung entstehen kann. |
| Geheimnisse | gitleaks über die ganze Git-Historie | siehe unten | Ein irgendwann eingecheckter Zugang |

Statt Larastan zu lockern, wenn ein Fehler lästig ist, wird der Fehler behoben. Ein niedrigeres Level braucht einen dokumentierten
Grund. Die Testabdeckung wird **nicht** gemessen und nie mit einer Schwelle erzwungen.

## Wie getestet wird

Die Pyramide ist unten breit: Die meisten Regeln stehen in **Feature-Tests**, die Seite, Persistenz und Berechtigung zusammen
prüfen. **Unit-Tests** gibt es für isolierte Logik. Wenige, gezielte **Browser-Tests** decken die Abläufe ab, auf die es
ankommt. Ein Happy Path reicht nie: Fehlerzustände, leere Zustände, Grenzen der Berechtigung und Sonderfälle bekommen eigene Tests.

- **Echte Datenbank.** Die Tests laufen gegen PostgreSQL, nie gegen SQLite oder eine Attrappe. Die Entwicklungsumgebung hat dafür die
  eigene Datenbank `nusszopf_testing` und einen eigenen Suchindex (`testing_items`); die Tests fassen Deine Entwicklungsdaten nie an.
- **Suche und Mail.** Meist mit Fakes (`Mail::fake()`, Scout-Fake); einige Tests der Gruppe `meilisearch` sprechen mit der echten Suche.
- **Berechtigungen.** Jede Zeile der [Berechtigungsmatrix](../security/authorization-matrix.md) braucht einen Test, der erlaubt, und
  einen, der verweigert.
- **Fehlerbehebungen.** Jeder bewusst behobene historische Fehler kommt mit einem Regressionstest in derselben Änderung
  ([Konventionen](konventionen.md#abweichungen-vom-original)).
- **Abfragen.** `QueryCountTest` sorgt dafür, dass eine Liste mit zwanzig Einträgen nicht mehr Abfragen braucht als eine mit einem.
  Neue Listen kommen dort hinein. Außerhalb des Betriebs wirft ein nachgeladener Beziehungszugriff (`preventLazyLoading`) einen Fehler.

## Browser-Tests

Sie liegen unter `tests/E2E/` mit Page Objects in `pages/`, Spezifikationen nach Rolle in `specs/` und gemeinsamen Konstanten in
`support/env.ts`. Jede Spezifikation registriert ihr **eigenes Konto** über die echte Registrierung, mit pro Lauf eindeutigen
Namen; es gibt keine geteilten Testkonten. Elemente werden über `data-testid` angesprochen.

```sh dev
docker compose -f compose.dev.yaml exec playwright npx playwright test                       # alles
docker compose -f compose.dev.yaml exec playwright npx playwright test --project=chromium   # eine Engine (chromium, firefox, webkit)
docker compose -f compose.dev.yaml exec playwright npx playwright test --project=mobile-safari --project=mobile-chrome --project=tablet-safari   # Telefone und Tablet
```

Die vollständige Suite hat Voraussetzungen:

- `SEARCH_PAGE_SIZE=5` in der `.env` (die Anwendung liest es bei der nächsten Anfrage neu), damit die Suchtests die letzte Seite
  erreichen. Der Test „Mehr laden“ wird sonst übersprungen. Playwright bekommt `E2E_SEARCH_PAGE_SIZE=5`.
- `E2E_MEILISEARCH_URL`, `E2E_MEILISEARCH_KEY` und `E2E_REINDEX_COMMAND`, die der Test zur Index-Wiederherstellung braucht.
  CI setzt alle diese Werte; ohne sie werden die Tests übersprungen.
- Newsletter- und Passwort-Tests teilen sich ein Kontingent von zehn Anfragen pro Adresse und 15 Minuten. Ein voller Lauf verbraucht
  fast alles, deshalb vor einem zweiten Lauf `php artisan cache:clear` ausführen.
- Tests von Telefonen und Tablet sind Emulationen, kein echtes Gerät. Vor einem Release wird zusätzlich von Hand auf einem echten
  iPhone und einem echten Android-Telefon geprüft.

## Visuelle Regression

Der Nusszopf soll wie das Original aussehen. Ein Screenshot-Vergleich prüft die 23 Seiten bei Telefon-, Tablet- und Desktop-Breite
gegen eingecheckte Vorbilder. Er braucht seinen eigenen Datensatz und **setzt Deine Entwicklungsdatenbank zurück**.

```sh dev
tests/Visual/reseed.sh
docker compose -f compose.dev.yaml exec -T playwright npx playwright test -c playwright.visual.config.ts
```

Nach einer beabsichtigten optischen Änderung schreibst Du die Vorbilder neu (`--update-snapshots=all`) und prüfst sie, bevor Du
sie einchecktst. Wie die Referenzbilder aus dem Original entstehen, steht in
[`docs/testing/visual-regression.md`](../testing/visual-regression.md).

## Barrierefreiheit und Sicherheit

Ein Release ist blockiert, solange ein axe-Scan auf einer Seite eine kritische oder schwere Verletzung findet, die Bedienung per
Tastatur (Fokusfalle, Escape, sichtbarer Fokus) lückenhaft ist oder eine Fehlermeldung nicht an ihr Feld gebunden ist. Der
CSP-Test schlägt bei jedem Verstoß fehl: Kein Inline-Skript, kein `on…=`-Handler, keine Ressource von fremden Hosts. Nimm ein
`data-`-Attribut und einen Listener in `resources/js/app.js`. Der Test `AuthorizationCoverageTest` listet jeden HTTP-Einstieg
mit seiner Schranke; eine neue Route gehört dort und in die Berechtigungsmatrix hinein. Details:
[`docs/testing/accessibility.md`](../testing/accessibility.md).

## Prüfungen für Releases

Manche Prüfungen bauen zwei Image-Sätze, brauchen ein zweites Docker (`docker:dind`) oder dauern Stunden. Sie laufen nicht in CI,
sondern als Schritt vor einem Release ([Versionen und Veröffentlichung](releases.md)):

| Skript | Prüft |
|---|---|
| `sh scripts/upgrade-test.sh <vorige version> [--suite]` | Das Upgrade von der letzten Version auf befüllten Daten: nichts geht verloren, Sitzungen und verschickte Links funktionieren weiter. |
| `sh scripts/restore-test.sh [--suite] [--rollback-from <vorige version>]` | Das Backup-Skript aus [Backup und Wiederherstellung](backup.md) und die Wiederherstellung auf einem leeren Host, auch als Rollback. Die Befehle stammen unverändert aus dieser Dokumentation. |
| `sh scripts/search-recovery-test.sh` | Vier Arten, den Suchindex zu verlieren, jeweils mit den Befehlen aus [Suchindex](betrieb.md#suchindex) behoben. |
| `sh scripts/queue-scheduler-test.sh` | Worker, Redis und Scheduler unter Ausfall (dauert etwa eine Stunde). |
| `P13_RECIPIENT=du@example.org sh scripts/mail-delivery-test.sh` | Sendet sieben echte Mails an ein Postfach, das Du nennst. |

Weil die Skripte die dokumentierten Codeblöcke selbst ausführen (sie holen sie über die Markierung
`<!-- P-10: … -->` bzw. `<!-- P-11: … -->` aus dem Handbuch), kann die Dokumentation nicht unbemerkt von dem abweichen, was geprüft ist.
Ändere diese Blöcke nur zusammen mit dem Skript.

## Geheimnisse und Abhängigkeiten von Hand prüfen

```sh dev
docker compose -f compose.dev.yaml exec workspace composer audit --locked
docker compose -f compose.dev.yaml exec workspace npm audit --omit=dev
docker run --rm -v "$PWD:/repo" ghcr.io/gitleaks/gitleaks:v8.30.1 git /repo --redact --log-opts="--all"
```

Dependabot schlägt Aktualisierungen wöchentlich vor.
