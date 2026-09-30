---
titel: Architektur
beschreibung: Ein Laravel-Monolith mit klaren Bereichen — wie eine Anfrage durch das System läuft und warum die Teile so geschnitten sind.
gruppe: entwickeln
reihenfolge: 2
---

# Architektur

Der Nusszopf ist **ein Repository, eine Laravel-Anwendung, ein Stack aus Docker Compose**. Es gibt keine Microservices, kein
getrenntes Frontend, keine GraphQL-Schicht und keine zentrale Steuerungsebene. Seiten entstehen auf dem Server mit Blade und
Livewire, und die Anwendung spricht direkt mit PostgreSQL.

Die Vorlage war anders gebaut: vier getrennt betriebene Dienste (Next.js-Frontend, zwei Auth0-Seiten, Hasura mit Auth0 und
Meilisearch), die über GraphQL sprachen. Diese Aufteilung war Folge der damaligen Technik (Vercel, Auth0, Hasura), nie eine
Produktanforderung. Ihr Verhalten, etwa die Berechtigungen, sind heute Laravel-Policies und Eloquent-Beziehungen. Die Abbildung
jedes alten Bausteins auf den neuen steht in [`docs/architecture/mapping.md`](../architecture/mapping.md).

## Die Bereiche

Die Grenzen folgen der Fachlichkeit, nicht technischen Schichten. „Modularer Monolith“ heißt hier: klare Namensräume und
Verzeichnisse innerhalb einer Anwendung, keine getrennt versionierten Pakete.

| Bereich | Enthält |
|---|---|
| **Projekte** | `Project`, `ProjectRequest` (bewusst nicht `Request`, das mit Laravels Klasse kollidiert) und `ProjectAnalytics`; die Livewire-Komponenten für Assistent, Bearbeiten, Detailseite und „Meine Projekte“; `ProjectPolicy` und `ProjectRequestPolicy`. Den Aufrufzähler erhöht nur der Server. |
| **Konten** | `User`, `UserPolicy`, Login, Registrierung und Passwort-Zurücksetzen (`App\Livewire\Auth`), Google-Login, E-Mail-Bestätigung, Profil, `AccountDeleter` und `AvatarUploader`. |
| **Newsletter** | `Lead`, die Anmeldeformulare, die beiden Newsletter-Mails und die Befehle `newsletter:export` und `newsletter:purge-unconfirmed`. Bewusst getrennt von den Konten, weil jemand Lead sein kann, ohne je ein Konto zu haben. |
| **Suche** | `App\Services\Search`, die Suchseite, `config/scout.php` und `search:reindex`. |
| **Inhaltsseiten** | Startseite, Rechtstexte (`LegalPageController`, `LegalText`), Fehlerseiten, `robots.txt` und Sitemap. Ohne Fachlogik. |
| **Plattform** | Die Blade-Komponentenbibliothek, `HealthChecker` und `nusszopf:health`, `SecurityHeaders`, `Operator` (Identität dieser Instanz), das Mail-Layout. Wird von allen benutzt. |

## Wie eine Anfrage läuft

**Lesen** (eine Projektseite ansehen):

```text
Browser → GET /projects/{id}
  → Livewire-Seite (Bereich Projekte)
     ├─ ProjectPolicy::view()        prüft die Sichtbarkeit
     ├─ Eloquent (PostgreSQL)        lädt Projekt, Gesuche, Besitzer:in
     ├─ Aufrufzähler erhöhen         nur serverseitig, einmal pro Browser
     └─ Blade-Komponenten            rendern die Seite
  → fertiges HTML
```

**Schreiben** (ein Projekt veröffentlichen):

```text
Browser → wire:click="publish"
  → Livewire-Methode (Bereich Projekte)
     ├─ ProjectPolicy::update()      nur die Besitzerin oder der Besitzer
     ├─ $project->update(...)        schreibt in PostgreSQL
     │    └─ Model-Event            legt einen Auftrag "Suchindex aktualisieren" in die Warteschlange
  → Komponente rendert neu, ohne die Seite zu laden
```

Ein privates Projekt antwortet jedem außer der Besitzerin oder dem Besitzer mit 404, auch auf den direkten Link.

## Hintergrundarbeit

Jede Arbeit, die nicht in der Anfrage erledigt werden muss, läuft über die Warteschlange in Redis, bearbeitet vom Dienst
`queue-worker`:

- **Suchindex.** Beim Speichern, Veröffentlichen, Verbergen und Löschen eines Projekts oder Gesuchs aktualisiert Laravel Scout den
  Index über die Warteschlange (`SCOUT_QUEUE=true`). Ein scheiternder Auftrag wird wiederholt und dann in `failed_jobs` behalten,
  nie stillschweigend verworfen.
- **Mail.** Jede Mail ist ein Mailable in der Warteschlange, mit derselben Wiederholungsregel. Eine Mail für ein Konto, das
  vor dem Versand gelöscht wurde, wird verworfen.
- **Konto löschen.** `AccountDeleter` löscht jedes Projekt einzeln über Eloquent (nur so laufen die Events, die es aus dem
  Suchindex nehmen), danach den Avatar, die Newsletter-Anmeldung und das Konto, alles in einer Transaktion.
- **Geplant** (Dienst `scheduler`): die beiden Herzschläge und die tägliche Bereinigung unbestätigter Newsletter-Anmeldungen.
  Neue geplante Arbeit kommt nur mit einer Entscheidung dazu, die sie braucht.

## Suche

Laravel Scout mit dem Meilisearch-Treiber. `Project` und `ProjectRequest` teilen **einen** Index, `items`: Ein Projekt ohne Gesuche
ist ein Dokument, ein Projekt mit Gesuchen besteht aus einem Dokument je Gesuch. Nur öffentliche Projekte gelangen hinein. Die
Einstellungen (durchsuchte Felder, Kategoriefilter, Sortierung, Trefferlimit) stehen versioniert in `config/scout.php` und werden
vom Start des Containers und von `search:reindex` angewendet, nie von Hand über eine API. Der Index ist abgeleitet, seine
Wiederherstellung ein Befehl: [Suchindex](betrieb.md#suchindex).

## Mail

Laravel Mail, ohne eigene Schicht darüber. Der Betreiber wählt mit `MAIL_MAILER` einen Transport. Resend ist empfohlen und geprüft; SMTP
funktioniert über denselben Weg. Die Vorlagen liegen versioniert in der Anwendung. Alle Mails sind reines HTML, so gewollt.

## Der Betrieb als Bauplan

Die ganze Anwendung ist **eine** auslieferbare Einheit: das `php-fpm`-Image, das auch Worker und Scheduler startet, und das
`web`-Image mit nginx. `postgres`, `redis` und `meilisearch` sind Hilfsdienste. Es gibt keinen zweiten Auth- oder Such-Dienst.

**Warum `web` ein eigenes Image ist und kein geteiltes Volume.** Die Vite-Assets werden einmal im Image der Anwendung gebaut, und
das `web`-Image entsteht daraus (`COPY --from=php-fpm /var/www/public`). Beide stammen aus demselben Quellstand, das Manifest kann also nie
auseinanderlaufen. Ein gemeinsames Volume für Assets wäre bei jedem Deployment veraltet, weil Docker ein neues benanntes Volume
nur beim ersten Anlegen aus dem Image füllt. Für Avatare gilt das nicht: Sie sind Laufzeitdaten und liegen deshalb in einem echten
Volume (`laravel-storage`), das `php-fpm` beschreibt und `web` nur lesend einbindet.

Das Image `php-fpm` läuft ohne root. Sein Einstiegspunkt weigert sich zu starten, wenn `APP_KEY` fehlt, migriert, baut die Caches
und wendet die Sucheinstellungen an ([Deployment](deployment.md#was-beim-neustart-automatisch-passiert)).

## Wo Entscheidungen stehen

Jede Grenze, Namenswahl und Infrastrukturentscheidung steht mit Begründung in
[`docs/rewrite/architecture-decisions.md`](../rewrite/architecture-decisions.md); das Register
[`docs/rewrite/decisions-register.md`](../rewrite/decisions-register.md) zeigt auf einen Blick, was entschieden und was offen ist.
Beides ist englisch.
