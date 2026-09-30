---
titel: Konventionen
beschreibung: Warum vieles so ist, wie es ist — Originaltreue, der Umgang mit historischen Fehlern, Beiträge und wie das Repository zusammenspielt.
gruppe: entwickeln
reihenfolge: 5
---

# Konventionen

Die wichtigste Regel steht vorweg: Der Nusszopf ist **kein Redesign**. Er ist eine getreue Neuimplementierung des historischen
Produkts, technisch erneuert. Vieles, was nach einer offenen Gestaltungsfrage aussieht, ist bereits durch das Original oder eine
dokumentierte Entscheidung beantwortet. Wer zuerst nachliest, spart sich eine Runde.

## Originaltreue

- **Verhalten.** Das historische Verhalten ist die Grundlage. Es wird nur geändert, wenn es nachweislich fehlerhaft oder
  unvollständig ist, wenn ein Dienst, auf den es sich stützte, verschwunden ist, oder wenn die Maintainer eine Änderung ausdrücklich
  genehmigen.
- **Aussehen.** Das historische Design ist verbindlich: Informationsarchitektur, Typografie, Farben, Abstände, Zustände, Reaktionen
  auf Bildschirmgrößen. Tailwind ist Werkzeug, um es nachzubauen, kein Anlass für ein neues Design.
- **Keine Erfindungen.** Kein neues Verhalten, keine neue Oberfläche und keine vereinfachten Abläufe ohne Beleg. Historische
  Konzepte werden nicht entfernt, weil sie alt wirken.
- **Kein SaaS.** Keine Mandanten, Abos, Abrechnung, Messung oder Pflicht-Fremddienste.

## Wo die Wahrheit liegt

Für das Produktverhalten, die Domäne, die Oberfläche und die Abläufe ist der ursprüngliche Nusszopf die Vorlage. Seine drei
archivierten Repositories liegen öffentlich in der GitHub-Organisation [Nusszopf](https://github.com/Nusszopf):
`web-nusszopf` (Frontend und E2E-Tests), `be-nusszopf` (Backend, Datenmodell, Suche) und `emails-nusszopf` (Mail-Vorlagen). Die
Fachseiten unter `docs/` fassen ihre Befunde zusammen und nennen die Fundstellen.

Brauchst Du für eine Änderung den Originalcode (etwa um eine offene Frage zu klären), klone die drei Repositories **neben** dieses
Repository, sodass sie unter `../historical/` liegen:

```sh dev
mkdir -p ../historical && cd ../historical
git clone https://github.com/Nusszopf/web-nusszopf.git
git clone https://github.com/Nusszopf/be-nusszopf.git
git clone https://github.com/Nusszopf/emails-nusszopf.git
```

Zum Betreiben und für die meiste Entwicklung brauchst Du sie nicht. Die Nachweise in den Fachseiten nennen die Dateien
darin (zum Beispiel `web-nusszopf/projects/webapp/…`); ohne die Klone kannst Du sie auf GitHub nachlesen.

Bei Widersprüchen gilt die Rangfolge: E2E-Tests und beobachtbares Verhalten, dann die echte Oberfläche, die UI-Bibliothek,
Abfragen des Frontends, Backend-Logik, Hasura-Metadaten, Suchkonfiguration, Mail-Vorlagen, Dokumentation, zuletzt Schlussfolgerung.
Aussagen in den Fachseiten sind mit **Confirmed** (belegt), **Inferred** (abgeleitet) oder **Unknown** (offen) gekennzeichnet.
Eine Schlussfolgerung wird nie stillschweigend zur Anforderung.

## Abweichungen vom Original

Jeder vermutete historische Fehler wird **vor** der Änderung in [`docs/rewrite/bugs.md`](../rewrite/bugs.md) mit einer
`BUG-NNN`-Nummer eingeordnet:

| Einstufung | Bedeutung |
|---|---|
| **Fix** | Nachweislich fehlerhaft. Das korrigierte Verhalten, der Beleg und der Regressionstest stehen in [`docs/rewrite/intentional-changes.md`](../rewrite/intentional-changes.md), **bevor** korrigiert wird. |
| **Preserve** | Das historische Verhalten bleibt, auch wenn es überrascht und nichts auf einen Fehler hindeutet. |
| **Unknown** | Nur mit einer Produktentscheidung zu klären. Sie steht in [`docs/rewrite/open-questions.md`](../rewrite/open-questions.md), und niemand rät. |
| **Replace** | Nur veraltete Infrastruktur; das Produktverhalten bleibt gleich. |

Ein Fehler ohne Eintrag wird nicht behoben. Eine Behebung ändert nie das Drumherum. Jede Korrektur kommt mit Regressionstest.
Eine offene Frage aus `open-questions.md` wird nicht geraten, sondern am Original geklärt oder an die Maintainer gegeben.

## Vor einer Änderung

1. Lies [`docs/rewrite/decisions-register.md`](../rewrite/decisions-register.md): was entschieden ist und was noch eine menschliche
   Entscheidung braucht. Entschiedenes wird nicht neu verhandelt, Offenes nicht heimlich entschieden.
2. Lies [`docs/rewrite/bugs.md`](../rewrite/bugs.md), falls der Bereich einen Eintrag hat.
3. Lies die Fachseite zum Bereich unter `docs/`: `domain/`, `design/`, `authentication/`, `search/`, `email/`, `security/`.
4. Öffne für alles Größere als eine Korrektur oder Dokumentationsänderung zuerst ein Issue.

Findest Du bei der Arbeit eine Tatsache, die einer belegten Aussage widerspricht, halte an und gleiche die Dokumentation mit der
Quelle ab, statt um den Widerspruch herumzuprogrammieren.

## Beiträge

Willkommen sind Korrekturen an Dokumentation, Tests, Werkzeugen, CI, Installation, Upgrade und Backup, sowie Stellen, an denen
der Nusszopf vom Original abweicht. Änderungen an Verhalten oder Design brauchen vorher die Zustimmung der Maintainer, auch die Behebung
eines historischen Fehlers. Nicht angenommen wird alles, was Selbst-Hosting erschwert oder SaaS-Konzepte einführt.

- Ein Pull Request behandelt ein Anliegen. Branche von `main` und öffne den Pull Request gegen `main`.
- Commit-Nachrichten folgen `typ(bereich): zusammenfassung` im Imperativ, etwa `fix(queue): …` oder `docs: …`, mit dem Grund im Text,
  wenn er nicht offensichtlich ist.
- `CHANGELOG.md` und Versionsnummern fasst Du nicht an; das gehört zum [Release-Prozess](releases.md). Beschreibe im Pull Request,
  was Betreiber:innen anders machen müssen.
- Keine Geheimnisse, keine `.env`-Dateien, keine Personendaten, und keine Schriften, Bilder oder Codeteile, deren Lizenz Du nicht
  geprüft hast. [`docs/legal/provenance.md`](../legal/provenance.md) sagt, was woher stammt; `NOTICE` nennt die Fremdlizenzen.
- Mit einem Beitrag lizenzierst Du ihn unter denselben Bedingungen wie das Projekt (GPL-3.0-oder-später). Eine
  Contributor-Vereinbarung gibt es nicht.

Ein Feature gilt erst als fertig, wenn Verhalten, Berechtigungen und Validierung stimmen, alle Zustände einer Seite (Laden, leer,
Fehler, Erfolg) existieren, Aussehen und Reaktionsverhalten passen, Tests bestehen — für wichtige Abläufe auch im Browser — und
die Dokumentation **in derselben Änderung** aktualisiert ist.

## Ein Pull Request im Überblick

Die Vorlage für Pull Requests fragt genau das ab. Alles in Kürze:

1. Fachseite gelesen, Lint, Larastan, Pest und die betroffenen Playwright-Projekte laufen ([Tests](tests.md)).
2. Neues Verhalten hat Tests, ein behobener Fehler einen Regressionstest.
3. Die Dokumentation ist mit aktualisiert, für Betreiber:innen-Änderungen auch [Deployment](deployment.md) und die Migrationshinweise.
4. Bei einem behobenen historischen Fehler existieren `BUG-NNN` und der genehmigte Eintrag in `intentional-changes.md`.
5. Keine Geheimnisse oder ungeprüften Fremdinhalte.
