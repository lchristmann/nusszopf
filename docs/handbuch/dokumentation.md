---
titel: Dokumentation pflegen
beschreibung: Wo welche Dokumentation liegt, was das Handbuch abdeckt, was nur englisch existiert — und wie Du beides aktuell hältst.
gruppe: hintergrund
reihenfolge: 2
---

# Dokumentation pflegen

Die Dokumentation des Nusszopf hat zwei Teile mit verschiedenen Aufgaben.

| Teil | Sprache | Zweck | Ort |
|---|---|---|---|
| **Handbuch** | Deutsch | Installieren, betreiben, entwickeln, verstehen | `docs/handbuch/`, veröffentlicht auf der Projektseite |
| **Spezifikation** | Englisch | Belege für das Verhalten des Originals, Entscheidungen, Fehlerregister, Prüfberichte | `docs/` (alle übrigen Ordner) |

Das Handbuch beschreibt den heutigen Stand und ändert sich mit dem Code. Die Spezifikation ist der Nachweis, wie es dazu kam. Sie ist
die Quelle der Wahrheit für das Produktverhalten, wenn das Handbuch und ein historischer Beleg auseinandergehen.

## Die Spezifikation im Überblick

| Ordner | Inhalt |
|---|---|
| [`domain/`](../domain/README.md) | Domänenmodell, Entitäten, Invarianten, Berechtigungen, Abläufe des Originals |
| [`design/`](../design/README.md) | Die genaue Beschreibung der historischen Oberfläche, `screen-specs.md` als Checkliste je Seite |
| [`authentication/`](../authentication/README.md), [`search/`](../search/README.md), [`email/`](../email/README.md) | Verhalten von Anmeldung, Suche und Mail |
| [`security/`](../security/README.md) | Sicherheitsanforderungen, darin die [Berechtigungsmatrix](../security/authorization-matrix.md) |
| [`journeys/`](../journeys/README.md) | Die Abnahmeabläufe |
| [`testing/`](../testing/README.md) | Teststrategie, Barrierefreiheit, visuelle Regression |
| [`rewrite/`](../rewrite/README.md) | Vorgehen, `decisions-register.md`, `bugs.md`, `intentional-changes.md`, `open-questions.md` und eine Seite je umgesetzter Etappe |
| [`release/`](../release/README.md) | Versionierung, Release-Prozess, Prüfberichte (`parity/`) |
| [`architecture/`](../architecture/README.md), [`legal/`](../legal/provenance.md) | Zuordnung alt zu neu, Herkunft von Code und Assets |
| [`deployment/`](../deployment/README.md), [`development/`](../development/README.md) | Verweise auf das Handbuch (früher die englische Betriebs- und Entwicklungsdokumentation) |

## Regeln

- **Ein Ort pro Sache.** Ein Betriebsverfahren steht im Handbuch und nur dort. Die Spezifikation verweist darauf, statt es zu
  wiederholen. Doppelte Fassungen laufen früher oder später auseinander.
- **Mit dem Code zusammen.** Wer Verhalten oder Betrieb ändert, aktualisiert die betroffene Seite in derselben Änderung, nicht
  danach.
- **Befehle sind geprüft.** Ein Befehl im Handbuch muss so laufen, wie er dasteht. Schreibe keine Abkürzungen (`…`) in Befehle
  und erfinde keine Verfahren. Markierte Blöcke werden von den Prüfskripten ausgeführt ([Tests](tests.md#prüfungen-für-releases)); ändere
  sie nur zusammen mit dem Skript.
- **Erst erklären, dann zeigen.** Ein Satz, wozu ein Befehl dient und wann man ihn braucht, geht dem Codeblock voraus. Kurze Blöcke, keine
  Terminal-Ausgaben in Bildschirmlänge.
- **Kein Flüchtiges.** Keine Interna, die morgen falsch sind: keine Dateilisten, keine Zeilennummern, keine Zwischenstände von Etappen.

## Schreibweise

Das Handbuch spricht die Leser:innen mit „Du“ an, so wie die Anwendung. Technische Begriffe, Frameworknamen, Befehle und Klassen
bleiben unübersetzt. Bewusste Kürze ist besser als Vollständigkeit; wo Betriebssicherheit es verlangt, darf es ausführlich sein.
Ein Projekt, das eine Person pflegt, redet nicht im Firmenwir.

**Codeblöcke** haben eine Beschriftung hinter der Sprache, die auch auf GitHub funktioniert:

````text
```sh server   → "Auf dem Server" (rosa): Produktion, im Installationsverzeichnis
```sh dev      → "Lokale Entwicklung" (türkis): auf Deinem Rechner im Repository
```sh          → neutral
````

**Hinweiskästen** nutzen die Syntax von GitHub, `> [!NOTE]`, `> [!TIP]`, `> [!IMPORTANT]`, `> [!WARNING]` und `> [!CAUTION]`. Auf der
Projektseite erscheinen sie als Karten mit deutscher Überschrift, auf GitHub in dessen Darstellung.

## Wie die Projektseite entsteht

`site/` ist eine eigene, kleine Node-Anwendung ohne PHP. Sie rendert die Seiten des Handbuchs bei jedem Build zu HTML
(`site/scripts/build-docs.mjs`) und benutzt dieselben Farb- und Typografie-Token wie die Anwendung. Ein kaputter Link oder eine
fehlende Sprungmarke bricht den Build ab.

```sh dev
cd site
npm install
npm run dev       # http://localhost:5173, lädt bei Änderungen an docs/handbuch/*.md neu
npm run build     # schreibt nach site/dist/
```

Jede Seite braucht Front Matter mit `titel`, `beschreibung`, `gruppe` (`einstieg`, `betreiben`, `entwickeln` oder `hintergrund`) und
`reihenfolge`. Die Seite `README.md` ist die Startseite des Handbuchs. Links zwischen Seiten schreibst Du als normale relative
Markdown-Links (`[Installation](installation.md)`); Links auf andere Dateien im Repository führen auf der Projektseite nach GitHub.
Der Workflow `.github/workflows/site.yml` veröffentlicht die Seite auf GitHub Pages, sobald sich `docs/handbuch/` ändert.
