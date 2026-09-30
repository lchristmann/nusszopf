---
titel: Geschäftslogik
beschreibung: Die Regeln, die den Nusszopf ausmachen — Konten, Projekte und Gesuche, Sichtbarkeit, Suche, Newsletter und Mails.
gruppe: entwickeln
reihenfolge: 3
---

# Geschäftslogik

Diese Seite fasst die Regeln zusammen, die man nicht aus einem einzelnen Blick in den Code sieht. Sie ist eine Landkarte,
keine Spezifikation: Für die genaue Rechtelage gilt die [Berechtigungsmatrix](../security/authorization-matrix.md), für das
historische Verhalten die Seiten unter [`docs/domain/`](../domain/README.md). Wo der Code und diese Seite auseinandergehen,
gilt der Code, und die Seite ist zu korrigieren.

## Die Rollen

Es gibt nur zwei: **Gäste** und **angemeldete Nutzer:innen**. Keine Rolle hat Sonderrechte, es gibt keine Administration in der
Oberfläche. Ein Konto darf nur mit seinen eigenen Projekten arbeiten. Die Regeln stehen als Laravel-Policies
(`ProjectPolicy`, `ProjectRequestPolicy`, `UserPolicy`); neue Routen kommen in die Berechtigungsmatrix und in die Routenliste des
Tests `AuthorizationCoverageTest`.

## Konten

- **Registrieren.** Benutzername (höchstens 15 Zeichen, ohne Leerzeichen, eindeutig), E-Mail-Adresse und ein Passwort mit
  mindestens 8 Zeichen, einem Klein- und einem Großbuchstaben, einer Ziffer und einem Sonderzeichen aus `!@#$%^&*`. Wer sich
  registriert, ist sofort angemeldet, bekommt eine Willkommens- und eine Bestätigungs-Mail und kann optional den Newsletter abonnieren.
- **E-Mail-Bestätigung ist keine Schranke.** Man kann ohne Bestätigung alles nutzen. Nur eines geht nicht: die eigene Adresse als
  öffentlichen persönlichen Kontakt eines Projekts eintragen. Dafür muss sie bestätigt sein.
- **Anmelden** geht mit Benutzername oder E-Mail-Adresse. Es gibt höchstens fünf Fehlversuche pro IP-Adresse und Minute. Nach fünf
  Fehlversuchen an einem Konto wird es 15 Minuten gesperrt, und die Besitzerin oder der Besitzer bekommt eine Mail mit dem Link
  „Das bin ich!“, der die Sperre aufhebt. Die Sitzung dauert acht Stunden ab der letzten Aktivität.
- **Passwort vergessen.** Ein Link per Mail; höchstens zehn Anfragen pro IP-Adresse und 15 Minuten. Nach einer Änderung enden alle
  anderen Sitzungen des Kontos bei ihrer nächsten Anfrage.
- **Google-Login** (optional). Ein bestehendes Konto wird nur verknüpft, wenn Google die Adresse ausdrücklich als bestätigt meldet;
  sonst könnte jemand mit einem unbestätigten Google-Konto ein fremdes Nusszopf-Konto übernehmen. Ein Google-Avatar füllt nur ein leeres
  Bild und überschreibt nie einen selbst hochgeladenen.
- **Grenzen gegen Missbrauch.** Registrierung: zehn Konten pro IP-Adresse und 15 Minuten (`NUSSZOPF_REGISTER_LIMIT`). Kontaktformular,
  Newsletter und „Passwort vergessen“: je zehn Anfragen pro IP-Adresse und 15 Minuten.
- **Konto löschen.** Löscht alle Projekte des Kontos, den Avatar und die Newsletter-Anmeldung derselben Adresse, in einem Schritt oder
  gar nicht.

## Projekte und Gesuche

Ein **Projekt** hat Titel (bis 40 Zeichen), Ziel (bis 150), eine formatierte Beschreibung, optional Team (formatiert) und Motto (bis
200 Zeichen), einen Ort und einen Zeitraum. Der **Ort** ist „ortsunabhängig“ oder ein ausgewählter Ort aus der Ortssuche. Der
**Zeitraum** ist „flexibel“ oder von/bis. Ein Projekt wird in vier Schritten angelegt (Beschreibung, Team und Motto, Gesuche,
Einstellungen) und später über drei Reiter bearbeitet: Beschreibung, Gesuche, Einstellungen.

Ein **Gesuch** gehört zu einem Projekt und hat Titel, Beschreibung und eine Kategorie: Mitstreiter:innen, Räume, Materialien,
Finanzielles oder Sonstiges. Ein Projekt darf null Gesuche haben.

**Sichtbarkeit.** `public` oder `private`, Standard im Assistenten ist `public`. Was das bewirkt:

| | öffentlich | privat |
|---|---|---|
| Detailseite | für alle sichtbar | für alle außer der Besitzerin oder dem Besitzer ein 404, auch mit direktem Link |
| Suche | ja | nie |
| Gesuche | wie das Projekt | wie das Projekt |

Wird ein Projekt privat gemacht oder gelöscht, verschwindet es samt Gesuchen aus dem Suchindex.

**Kontakt.** Zwei Möglichkeiten: „Persönlich“ zeigt die E-Mail-Adresse der Besitzerin oder des Besitzers als `mailto:` (nur mit
bestätigter Adresse), „Über Nusszopf“ zeigt ein Kontaktformular, dessen Nachricht per Mail an die Besitzerin oder den Besitzer geht,
ohne dass deren Adresse sichtbar wird. Das ausgefüllte Formular kann sich auf ein bestimmtes Gesuch beziehen.

**Aufrufe.** Die Detailseite zählt Besuche: einmal pro Browser (per Cookie), nie die der Besitzerin oder des Besitzers. Nur der
Server erhöht den Zähler. Im Original konnte ihn jeder beliebig überschreiben.

**Melden.** „Projekt melden“ ist ein `mailto:` an die Kontaktadresse der Instanz, mit der Projekt-ID im Betreff. Es gibt kein
Meldeformular in der Anwendung.

## Suche

Die Suche läuft über Meilisearch (ein Index `items`, siehe [Architektur](architektur.md#suche)). Sie durchsucht Titel, Ziel,
Beschreibung, Ort, Team, Motto und Autor:in sowie Titel und Beschreibung der Gesuche, lässt sich nach Gesuch-Kategorie filtern und
sortiert Treffer bei gleicher Relevanz nach dem Zeitpunkt der letzten Änderung. Eine Anfrage darf höchstens 30 Zeichen lang sein. Treffer eines Projekts werden
in einer Karte zusammengefasst. Ist die Suche gestört, zeigt die Seite „Verzopft…“ und keine Treffer; die Seite bleibt bedienbar.

Wichtig für Änderungen: Ein Projekt oder Gesuch aktualisiert den Index beim Speichern automatisch über die Warteschlange.
Wer Suchfelder ändert, muss den Index nach dem Einspielen neu aufbauen (siehe [Deployment](deployment.md#was-beim-neustart-automatisch-passiert)).

## Newsletter

Der Nusszopf verwaltet nur die **Liste**, versendet aber keine Ausgaben (siehe [Betrieb](betrieb.md#newsletter-anmeldungen)).

- **Double-Opt-in auf jedem Weg.** Ob über das Formular auf der Startseite, bei der Registrierung oder im Profil: Erst der Klick auf
  den Bestätigungslink in der Mail macht eine Anmeldung gültig. Der Link ist sieben Tage gültig.
- **Einwilligung nachvollziehbar.** Zu jeder Anmeldung wird gespeichert, wann sie angefordert und bestätigt wurde, auf welchem Weg
  (`form`, `registration`, `profile`) und welcher Version des Datenschutztextes zugestimmt wurde. Eine IP-Adresse wird nicht gespeichert.
- **Abmelden** geht über den Link in jeder Mail oder mit der Adresse auf `/newsletter/unsubscribe/lead`.
- **Aufräumen.** Unbestätigte Anmeldungen werden nach 14 Tagen gelöscht. Pro Adresse werden höchstens drei Newsletter-Mails pro
  Stunde verschickt.
- Eine Anmeldung gehört zu einer Adresse, nicht zu einem Konto: Man kann Lead sein, ohne ein Konto zu haben.

## Mails

Sieben Mails verschickt die Anwendung: Willkommen, E-Mail bestätigen, Passwort zurücksetzen (`ChangePasswordMail`), Konto gesperrt
(`BlockedAccountMail`), Kontaktanfrage, Newsletter bestätigen und Newsletter abmelden. Alle laufen über die Warteschlange, alle sind
reines HTML, und alle nennen als Kontakt die Adresse aus `NUSSZOPF_CONTACT_EMAIL`.

## Was bewusst nicht existiert

Keine Administration, keine Rollen über „angemeldet“ hinaus, kein Apple-Login (im Original angelegt, nie angeschlossen), keine
Zahlungen und keine Mandanten. Ein Nusszopf wird von einer Person oder Gruppe betrieben, nicht als Dienst für viele. Solche
Konzepte einzuführen ist ausdrücklich ausgeschlossen ([Konventionen](konventionen.md)).
