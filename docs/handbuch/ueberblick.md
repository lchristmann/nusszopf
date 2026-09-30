---
titel: Überblick
beschreibung: Was der Nusszopf ist, aus welchen Bausteinen er besteht und welche Begriffe Du kennen solltest.
gruppe: einstieg
reihenfolge: 1
---

# Überblick

## Was der Nusszopf ist

Wer ein Projekt hat, stellt es auf dem Nusszopf ein und schreibt dazu, was noch fehlt. Jede dieser Lücken ist ein
**Gesuch**: Mitstreiter:innen, Räume, Materialien, Finanzielles oder Sonstiges. Andere finden das Projekt über die Suche,
lesen die Gesuche und melden sich.

Der Nusszopf ist freie Software (GPL-3.0-oder-später) und gedacht für Communities, Schulen, Firmen und Vereine, die ein
eigenes schwarzes Brett für Ideen betreiben wollen. Es gibt keine zentrale Instanz, keine Mandanten und keine Abos. Wer
den Nusszopf betreibt, betreibt ihn ganz.

Die Anwendung ist die Neuimplementierung des ursprünglichen Nusszopf. Aussehen und Verhalten sind bewusst dieselben
geblieben; ersetzt wurde die veraltete Technik darunter. Mehr dazu unter [Konventionen](konventionen.md).

## Begriffe

| Begriff | Bedeutung |
|---|---|
| **Projekt** | Eine Idee mit Titel, Ziel, Beschreibung, optional Team und Motto, Ort und Zeitraum. Gehört genau einer Person. |
| **Gesuch** | Eine konkrete Lücke eines Projekts, mit Titel, Beschreibung und einer von fünf Kategorien. |
| **Sichtbarkeit** | Ein Projekt ist entweder `public` (in der Suche und über den Link erreichbar) oder `private` (nur für die Besitzerin oder den Besitzer). |
| **Kontakt** | Wie sich Interessierte melden: „Persönlich“ (eigene E-Mail-Adresse) oder „Über Nusszopf“ (ein Kontaktformular; die Nachricht geht per Mail an die Besitzerin oder den Besitzer, dessen Adresse bleibt verborgen). |
| **Lead** | Eine Newsletter-Anmeldung. Sie ist unabhängig vom Konto: Man kann angemeldet sein, ohne ein Konto zu haben. |
| **Instanz** | Eine laufende Installation. Jede Instanz hat ihre eigenen Betreiber:innen, Rechtstexte und Daten. |

Es gibt **keine Administrator:innen**. Jedes Konto hat dieselben Rechte, auch das erste. Wer eine Instanz betreibt, tut das
auf dem Server, nicht in der Oberfläche.

## Woraus der Nusszopf besteht

Der Nusszopf ist **eine** Laravel-Anwendung, kein Verbund aus Diensten. Sie braucht drei Hilfsdienste:

| Baustein | Wofür |
|---|---|
| Laravel 13 mit PHP 8.5 | Die Anwendung selbst. Seiten werden auf dem Server mit Blade und Livewire 4 gerendert. |
| PostgreSQL | Die Daten: Konten, Projekte, Gesuche, Newsletter-Anmeldungen. Die einzige Quelle der Wahrheit. |
| Redis | Sitzungen, Cache und die Warteschlange für Hintergrundarbeit (Mails, Suchindex). |
| Meilisearch | Die Suche. Ihr Index ist abgeleitet und lässt sich jederzeit aus PostgreSQL neu aufbauen. |
| Tailwind CSS 4 | Das Styling, das das historische Design nachbaut. |

Auf einem Server laufen sieben Container: `web` (nginx), `php-fpm`, `queue-worker`, `scheduler`, `postgres`, `redis` und
`meilisearch`. [Architektur](architektur.md) erklärt, wer was tut.

## Einen Nusszopf ausprobieren

Die Demo unter [nusszopf.org](https://nusszopf.org) ist der echte Nusszopf mit erfundenen Beispielprojekten. Man
braucht kein Konto: Ein Knopf meldet Dich an einem gemeinsamen Demo-Konto an, eine geführte Tour zeigt die wichtigsten
Seiten, und alle Daten werden stündlich zurückgesetzt. Wie das funktioniert, steht unter [Demo-Modus](demo.md).

## Das Repository auf einen Blick

| Ort | Inhalt |
|---|---|
| `app/`, `resources/`, `routes/`, `database/` | Die Laravel-Anwendung |
| `docker/`, `docker-compose.yaml`, `compose.dev.yaml` | Images und die beiden Compose-Stacks (Betrieb und Entwicklung) |
| `scripts/` | `install.sh` für Betreiber:innen und die Prüfskripte für Releases |
| `tests/` | Pest-Tests, Playwright-Tests und die Prüfungen für Upgrade, Restore und Queue |
| `site/` | Diese Projektseite samt Handbuch, unabhängig von der Anwendung |
| `docs/handbuch/` | Dieses Handbuch |
| `docs/` (übrige Ordner) | Die englische Spezifikation des Rewrites |
