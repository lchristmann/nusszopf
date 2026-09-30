---
titel: Konfiguration
beschreibung: Mailversand, Rechtstexte, Ortssuche, Google-Login und alle Einstellungen der .env im Überblick.
gruppe: betreiben
reihenfolge: 2
---

# Konfiguration

Alle Einstellungen stehen in der `.env` neben der `docker-compose.yaml`. Die Datei `.env.production.example`, die
`install.sh` mitbringt, ist die Referenz: Jede Einstellung hat dort einen Standardwert oder ist als `REQUIRED` markiert.

> [!IMPORTANT]
> Nach jeder Änderung an der `.env` führst Du `docker compose up -d` aus, nicht `docker compose restart`. Ein Container
> behält die Umgebung, mit der er erstellt wurde; nur `up -d` erstellt die Container neu, deren Einstellungen sich
> geändert haben.

```sh server
docker compose up -d
```

## Was Du selbst setzen musst

`install.sh` erzeugt `APP_KEY`, `DB_PASSWORD`, `MEILISEARCH_KEY` und `HEALTH_TOKEN` und trägt Version und Adresse ein.
Übrig bleiben drei Angaben:

| Einstellung | Bedeutung |
|---|---|
| `MAIL_FROM_ADDRESS` | Deine Absenderadresse. Pflicht, ohne sie startet Compose nicht. |
| `MAIL_MAILER` und die Zugangsdaten dazu | Wie Mails verschickt werden, siehe unten. |
| `APP_BIND=127.0.0.1` | Nur nötig, wenn ein Reverse Proxy auf demselben Server läuft. |

## Mailversand

Jede Mail des Nusszopf (Willkommen, Bestätigung, Passwort zurücksetzen, Kontaktformular, Newsletter-Bestätigungen) wird in
die Warteschlange gelegt und vom `queue-worker` verschickt. Der Nusszopf benutzt dafür Laravels Mailer und fügt nichts
hinzu. Du wählst mit `MAIL_MAILER` einen aus und gibst seine Zugangsdaten an.

**Resend ist die Empfehlung.** Es ist der Anbieter, mit dem echte Zustellung an ein fremdes Postfach auf dem Produktions-Stack
geprüft wurde, und es braucht eine einzige Einstellung. Ein SMTP-Relay funktioniert über denselben Mechanismus, wurde aber
nur gegen einen Test-Mailserver ohne Zertifikatsprüfung getestet, nicht gegen ein echtes Relay mit TLS.

```env
# Resend (empfohlen)
MAIL_MAILER=resend
RESEND_API_KEY=re_…
MAIL_FROM_ADDRESS=hallo@nusszopf.example.org
```

```env
# SMTP-Relay
MAIL_MAILER=smtp
MAIL_HOST=smtp.example.org
MAIL_PORT=587          # 587 mit STARTTLS, 465 mit implizitem TLS
MAIL_USERNAME=…
MAIL_PASSWORD=…
MAIL_SCHEME=smtps      # nur bei Port 465 nötig
MAIL_FROM_ADDRESS=hallo@nusszopf.example.org
```

Zwei Regeln sind wichtig:

- **Die Absenderdomain muss bei Deinem Anbieter verifiziert sein.** Resend lehnt jede andere Domain ab
  (`The <domain> domain is not verified`); die Mail landet dann nach fünf Versuchen in `failed_jobs`. Welche
  DNS-Einträge nötig sind (DKIM, ein Return-Path, meist auch SPF und DMARC), sagt Dein Anbieter.
- **Zertifikate werden immer geprüft.** Es gibt keine Einstellung, das abzuschalten.

Alle Mails sind reines HTML ohne Text-Teil. Das ist gewollt, keine Fehlkonfiguration. Wie die Mails in Gmail, Outlook und
Apple Mail aussehen, ist noch nicht geprüft; in Proton Mail schon.

Wenn Du einen `no-reply@`-Absender benutzt, setze zusätzlich `NUSSZOPF_CONTACT_EMAIL` auf ein Postfach, das Du liest.
Sonst verweisen Fehlerseite, Kontaktkarte und Mail-Fußzeilen Menschen auf die no-reply-Adresse. Mails des Kontaktformulars
tragen die Adresse der Absender:innen als `Reply-To`, „Antworten“ funktioniert also in jedem Fall.

## Rechtstexte

Impressum (`/legalNotice`), Rechtliches (`/legalPolicy`) und Datenschutz (`/privacy`) zeigen **Deinen** Text. Der Nusszopf
liefert keinen mit. Lege drei Markdown-Dateien in den Ordner `legal/` neben der `docker-compose.yaml`; `install.sh` legt ihn
an, der Stack bindet ihn schreibgeschützt ein.

| Datei | Seite |
|---|---|
| `legal/legal-notice.md` | Impressum |
| `legal/legal-policy.md` | Rechtliches (Nutzungsbedingungen) |
| `legal/privacy.md` | Datenschutz |

Die Überschrift der Seite ist fest, die Datei liefert den Inhalt: `##`-Überschriften, Absätze, Listen und Links; ein
Zeilenende mit zwei Leerzeichen erzwingt einen Umbruch. Rohes HTML wird als Text angezeigt. Änderungen erscheinen beim
nächsten Aufruf, ohne Neustart. Fehlt eine Datei oder ist sie leer, steht auf der Seite „Dieser Text wurde von den
Betreiber:innen dieser Nusszopf-Instanz noch nicht hinterlegt.“

Als Vorlage dienen die Texte des ursprünglichen Betreibers unter
[`docs/deployment/legal-examples/`](../deployment/legal-examples/README.md). Veröffentliche sie nicht unverändert. Dein
Datenschutztext muss beschreiben, was Deine Instanz wirklich tut: welchen Mailanbieter Du benutzt, ob LocationIQ und Google-Login
aktiv sind und dass der Newsletter ein Double-Opt-in hat. Ändert er sich, erhöhe `NEWSLETTER_CONSENT_VERSION`
(siehe [Newsletter](betrieb.md#newsletter-anmeldungen)). Sichere den Ordner zusammen mit der `.env`.

## Ortssuche (LocationIQ)

Wer ein Projekt an einen Ort bindet („Ortsgebunden“), sucht ihn über die Autocomplete-API von
[LocationIQ](https://locationiq.com), wie im ursprünglichen Nusszopf, und zwar nur deutsche Städte, Gemeinden und Dörfer.
Es gibt einen kostenlosen Tarif. Trage den Schlüssel als `LOCATIONIQ_KEY` ein. Die Anfrage stellt der Server, der Schlüssel
gelangt nie in den Browser.

Ohne Schlüssel zeigt die Ortssuche keine Vorschläge, und ein Projekt lässt sich nicht an einen Ort binden.
„Ortsunabhängige“ Projekte sind nicht betroffen.

## Google-Login

Der Login mit Google braucht einen OAuth-2.0-Client aus der
[Google Cloud Console](https://console.cloud.google.com/apis/credentials). Trage als autorisierte Weiterleitungs-URI
`<APP_URL>/auth/google/callback` ein und setze `GOOGLE_CLIENT_ID` und `GOOGLE_CLIENT_SECRET`. Fehlt eine der beiden
Angaben, verschwindet der Google-Knopf, die Routen antworten mit 404, und der Login per Name oder E-Mail ist davon nicht
berührt. `GOOGLE_REDIRECT_URI` brauchst Du nur, wenn die Instanz unter einer anderen Adresse als `APP_URL` erreichbar ist.

## Alle Einstellungen

**Fett** sind die, die Du setzen musst; `install.sh` füllt die mit „erzeugt“.

| Einstellung | Standard | Bedeutung |
|---|---|---|
| **`NUSSZOPF_VERSION`** | von `install.sh` | Die laufende Version. Ändert sich bei jedem [Deployment](deployment.md). |
| `APP_NAME` | `Nusszopf` | Name der Anwendung und Standard für den Absendernamen `MAIL_FROM_NAME`. |
| `APP_ENV`, `APP_DEBUG` | `production`, `false` | Nicht ändern. `APP_DEBUG=true` zeigt Interna auf Fehlerseiten. |
| **`APP_KEY`** | erzeugt | Signiert Sitzungen, Cookies und jeden Link in Mails. Bewahre ihn im Backup auf; ein neuer macht verschickte Links ungültig. |
| **`APP_URL`** | von `install.sh` | Die exakte öffentliche Adresse. |
| `APP_LOCALE`, `APP_FALLBACK_LOCALE`, `BCRYPT_ROUNDS` | `de`, `en`, `12` | Sprache und Kosten des Passwort-Hashings. Das Produkt ist deutsch. |
| `APP_BIND`, `APP_PORT` | `0.0.0.0`, `8080` | Wo `web` auf dem Host lauscht. Mit Proxy auf demselben Server `127.0.0.1`. |
| `TRUSTED_PROXIES` | Loopback und private Netze | Welchen Proxys Adresse und Protokoll geglaubt werden. Nie `*` bei erreichbarem Port. |
| `SESSION_SECURE_COOKIE` | `true` | Cookies nur über https. |
| `LOG_CHANNEL`, `LOG_LEVEL` | `stderr`, `warning` | `docker compose logs` zeigt das Log der Anwendung. |
| `DB_*` | Werte des mitgelieferten PostgreSQL | Ändere sie nicht. `DB_PASSWORD` wird beim Anlegen der Datenbank verwendet. |
| **`DB_PASSWORD`** | erzeugt | Ein späteres Ändern braucht eine passende Änderung in PostgreSQL. |
| `SESSION_DRIVER`, `SESSION_LIFETIME`, `CACHE_STORE`, `QUEUE_CONNECTION`, `REDIS_HOST` | `redis`, `480`, `redis`, `redis`, `redis` | Sitzungen (8 Stunden, gleitend, wie im Original), Cache und Warteschlange im mitgelieferten Redis. |
| `SCOUT_DRIVER`, `SCOUT_QUEUE`, `MEILISEARCH_HOST` | `meilisearch`, `true`, `http://meilisearch:7700` | Die Suche. Änderungen am Index laufen über die Warteschlange und werden wiederholt. |
| **`MEILISEARCH_KEY`** | erzeugt | Master-Schlüssel von Meilisearch, mindestens 16 Zeichen. |
| `LOCATIONIQ_KEY` | leer | Ortssuche, siehe oben. |
| `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`, `GOOGLE_REDIRECT_URI` | leer | Google-Login, siehe oben. |
| `MAIL_MAILER`, `RESEND_API_KEY`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_FROM_NAME` | `smtp`, leer, leer, `587`, leer, leer, `${APP_NAME}` | Mailversand, siehe oben. |
| **`MAIL_FROM_ADDRESS`** | leer | Absender jeder Mail. |
| `NUSSZOPF_CONTACT_EMAIL` | leer (nimmt `MAIL_FROM_ADDRESS`) | Die Adresse, die überall steht, wo die Anwendung „schreib uns“ sagt. |
| `NUSSZOPF_LEGAL_PATH` | leer (nimmt `./legal`) | Ordner der Rechtstexte. Nur nötig, wenn Du ihn woanders einbindest. |
| `NUSSZOPF_REGISTER_LIMIT` | leer (10) | Neue Konten pro IP-Adresse und 15 Minuten. |
| `NUSSZOPF_DEMO` | `false` | [Demo-Modus](demo.md), nur für Vorführ-Instanzen. |
| `NUSSZOPF_SOURCE_URL` | das Upstream-Repository | Wohin die Startseite bei „Mitmachen“ verlinkt. Nützlich bei einem Fork. |
| `NUSSZOPF_DOCS_URL` | `https://lchristmann.github.io/nusszopf/handbuch` | Die Handbuch-Website, auf die Startseite und geführte Tour verlinken (ohne abschließenden Schrägstrich). Nützlich, wenn ein Fork sein eigenes Handbuch veröffentlicht. |
| `NEWSLETTER_CONSENT_VERSION` | `1` | Version Deines Datenschutztextes; wird bei jeder Newsletter-Einwilligung gespeichert. |
| `HEALTH_TOKEN` | erzeugt | Bearer-Token, mit dem `/health` Version und Details verrät. |

`LOCATIONIQ_URL` und `MAIL_SCHEME` liest die Anwendung ebenfalls, sie stehen aber nicht in der Vorlage. Die erste ist für
einen kompatiblen Ersatzdienst gedacht, die zweite für SMTP über Port 465.

## Weiter

- Die Instanz läuft und ist konfiguriert? Dann sichere sie: [Backup und Wiederherstellung](backup.md).
- Eine neue Version einspielen: [Deployment](deployment.md).
