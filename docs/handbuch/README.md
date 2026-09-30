---
titel: Handbuch
beschreibung: Alles, was Du brauchst, um den Nusszopf zu betreiben, weiterzuentwickeln und zu verstehen — auf Deutsch, mit Befehlen, die geprüft sind.
gruppe: einstieg
reihenfolge: 0
---

# Handbuch

Der Nusszopf ist eine Community-Plattform, auf der Menschen Projekte einstellen und dazuschreiben, was ihnen noch
fehlt: Mitstreiter:innen, Räume, Materialien, Geld oder etwas anderes. Dieses Handbuch beschreibt die Software so, wie
sie heute ist. Es ist für Menschen geschrieben, die den Quellcode nie gesehen haben.

## Womit möchtest Du anfangen?

**Ich will einen Nusszopf betreiben.** Du brauchst einen Server mit Docker und eine Domain, sonst nichts.

1. [Installation](installation.md): vom leeren Verzeichnis zur laufenden Instanz.
2. [Konfiguration](konfiguration.md): Mailversand, Rechtstexte und alle Einstellungen der `.env`.
3. [Deployment](deployment.md): eine neue Version einspielen und, wenn nötig, zurückgehen.
4. [Betrieb](betrieb.md), [Backup und Wiederherstellung](backup.md) und [Fehlerbehebung](fehlerbehebung.md) für den Alltag.

**Ich will mitentwickeln.** Du brauchst Docker, Docker Compose und Git, keine PHP- oder Node-Installation.

1. [Lokale Entwicklung](entwicklung.md): der erste Start und die Befehle, die Du täglich brauchst.
2. [Architektur](architektur.md) und [Geschäftslogik](geschaeftslogik.md): wie die Teile zusammenhängen und welche Regeln gelten.
3. [Tests und Qualität](tests.md) und [Konventionen](konventionen.md): was vor einem Pull Request laufen muss und warum vieles so ist, wie es ist.

**Ich will nur verstehen, was das ist.** Lies den [Überblick](ueberblick.md) und probiere die
[Demo auf nusszopf.org](https://nusszopf.org) aus. Sie ist der echte Nusszopf mit erfundenen Beispielprojekten.

## Wie dieses Handbuch aufgebaut ist

Jede Seite erklärt zuerst in Worten, was passiert und warum, und zeigt dann den Befehl. Befehle sind beschriftet:

```sh server
# Diese Befehle laufen auf dem Server, im Verzeichnis Deiner Installation.
docker compose ps
```

```sh dev
# Diese Befehle laufen auf Deinem Rechner, im Repository, für die lokale Entwicklung.
docker compose -f compose.dev.yaml ps
```

> [!NOTE]
> Server und Entwicklung benutzen zwei verschiedene Compose-Dateien. Auf dem Server heißt sie `docker-compose.yaml` und
> Compose findet sie von allein. Für die Entwicklung gibst Du immer `-f compose.dev.yaml` an. Vermischen solltest Du die
> beiden nicht.

Hinweiskästen wie dieser sind sparsam gesetzt: **Tipp** für Abkürzungen, **Achtung** für Dinge, die Daten kosten können.

## Was dieses Handbuch nicht ist

Der Nusszopf ist die Neuimplementierung einer älteren Anwendung. Wie das historische Produkt aussah, warum eine
Entscheidung so fiel und welche Mängel des Originals behoben wurden, steht in der englischsprachigen Spezifikation unter
[`docs/`](../README.md). Das Handbuch verweist dorthin, wo es hilft, wiederholt sie aber nicht. Wo was steht, erklärt
[Dokumentation pflegen](dokumentation.md).
