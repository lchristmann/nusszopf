---
titel: Demo-Modus
beschreibung: Wie eine öffentliche Vorführ-Instanz mit erfundenen Daten und geführter Tour funktioniert — und warum eine normale Installation ihn nie braucht.
gruppe: betreiben
reihenfolge: 7
---

# Demo-Modus

Eine Demo-Instanz lässt jeden den echten Nusszopf ausprobieren, ohne ein Konto anzulegen, mit erfundenen Daten. Die Demo des
Projekts läuft auf [nusszopf.org](https://nusszopf.org). Der Modus ist **standardmäßig aus**; eine normale Installation
braucht ihn nie.

## Einschalten

Trage in die `.env` ein:

```env
NUSSZOPF_DEMO=true
```

Starte den Stack neu (`docker compose up -d`) und lege die Beispieldaten einmalig an. Danach baut der Scheduler sie stündlich neu auf.

```sh server
docker compose exec php-fpm php artisan demo:reset
```

## Was der Modus ändert

- **Ein gemeinsames Konto ohne Passwort.** `demo:reset` legt den Nutzer `demo` an, mit fünf erfundenen Projekten (vier
  öffentlich, ein privater Entwurf) und Gesuchen in allen Kategorien. Da das Konto kein Passwort hat, kann man sich mit dem Formular
  nie darin anmelden. Der einzige Weg ist der Knopf „Demo ausprobieren“ auf der Startseite; ohne Demo-Modus antwortet er mit 404. Wer
  schon mit einem echten Konto angemeldet ist, wird nie umgeschaltet.
- **Keine echten Daten.** Alles ist erfunden. Die Kontaktadresse der Projekte ist `kontakt@example.org`, und kein Projekt nutzt
  „Über Nusszopf“, sodass keine Nachricht aus der Demo ein echtes Postfach erreichen kann.
- **Gegen Zerstörung geschützt.** Niemand kann das Demo-Konto löschen oder seinen Avatar ändern, und seine Adresse lässt sich nicht für
  den Newsletter anmelden. Projekte anlegen, bearbeiten, veröffentlichen und löschen darf man dagegen — das ist der Zweck. Alle teilen
  sich das Konto und sehen einander.
- **Stündlicher Reset.** `demo:reset` (nur im Demo-Modus geplant) löscht das Demo-Konto samt Inhalt über Eloquent, sodass der Suchindex
  folgt, und baut es neu auf. Andere Konten fasst es nicht an. Alles, was Besucher:innen getan haben, verschwindet zur nächsten
  vollen Stunde, und wer gerade angemeldet ist, wird abgemeldet. Ohne Demo-Modus verweigert der Befehl seine Arbeit; von Hand
  bewirkt er einen sofortigen Reset.
- **Keine echte Adresse wird gesammelt.** Die Registrierung ist aus (der Reiter erklärt das und bietet den Demo-Knopf an),
  Google-Login antwortet mit 404, das Newsletter-Formular fehlt auf der Startseite, und das Kontaktformular sendet keine
  Nachricht. „Passwort vergessen“ und Abmelden wirken nur auf bereits vorhandene Adressen. Eine Restlücke bleibt: Wer als `demo` ein
  Projekt anlegt, kann eine Adresse als persönlichen Kontakt eintragen. Sie ist öffentlich sichtbar und wird zum nächsten stündlichen
  Reset gelöscht.

## Die geführte Tour

Als `demo` angemeldet, erscheint unten links der Knopf „Geführte Tour“. Sie besteht aus acht Schritten über die echten Seiten:
Meine Projekte, ein Projekt starten, die Suche und ihr Filter, die Detailseite mit Gesuchen und die Einstellungen mit dem Newsletter.
Sie ist ein kleines Overlay ohne Bibliothek: Sie hebt das echte Element hervor, die Seite bleibt bedienbar, „Tour beenden“ oder
Escape beendet sie jederzeit, und sie funktioniert per Tastatur und auf dem Telefon. Den Fortschritt merkt sie sich im
`sessionStorage`.

Ihre Ziele sind die `data-test`-Attribute der Seiten. Ändert jemand eine Seite so, dass ein Schritt ins Leere zeigt, schlägt
`tests/E2E/specs/demo/demo-tour.spec.ts` fehl.

## Betriebshinweise

- Die Demo ist so öffentlich wie jede Instanz: Behalte sie im Blick und gib ihr eigene Rechtstexte.
- Tests: `tests/Feature/Demo/DemoTest.php` und die Browser-Spezifikation, die einen Stack im Demo-Modus braucht:

```sh dev
docker compose -f compose.dev.yaml exec -e E2E_DEMO=1 playwright npx playwright test specs/demo
```
