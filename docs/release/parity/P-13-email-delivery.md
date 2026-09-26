# P-13 E-mail delivery (2026-09-26)

Exit evidence (`master-roadmap.md` §4): "Every mail type received and rendered in at least Gmail, Outlook and Apple
Mail; docs list operator DNS prerequisites."

**Status: Done (closed by the maintainer 2026-09-26), with Gmail, Outlook and Apple Mail deferred to P-16, not
waived.** All seven mail types were delivered through a real provider on the production stack and inspected by the
maintainer in Proton Mail, the operator documentation exists, and the failure path was observed. The roadmap names
Gmail, Outlook and Apple Mail; none of them was available, so their rendering has **not** been verified (section 9).
The maintainer closed the phase on that basis. The three checks are carried to P-16 and remain owed.

The maintainer's scope, in short: use the Resend account as the real transactional provider; keep Mailpit for
development; send every mail type the application sends through the production queue to one test mailbox; verify
what can be verified; do not mark any client check as passed before it is observed; do not repeat P-12; do not start
P-14.

## 1. Scope

- The real provider is **Resend** (its HTTP API, `MAIL_MAILER=resend`), because no SMTP relay was available. A generic
  SMTP relay with real TLS was therefore **not** exercised (section 10).
- The test recipient is one mailbox at Proton Mail, given by the maintainer for this phase only. It is passed to the
  scripts as `P13_RECIPIENT`; it is not a default anywhere in the repository, and no document requires it.
- The sender is an address on a domain the maintainer verified in Resend, set in the untracked `.env`
  (`MAIL_FROM_ADDRESS`). The API key is in the same file, is copied by the script into a scratch `.env` without being
  printed, and appears in no log, commit or document.

## 2. Configuration model and environment

`config/mail.php` and `config/services.php` already had a `resend` mailer and `services.resend.key`
(`RESEND_API_KEY`), but `resend/resend-php`, which Laravel's transport needs, was not installed (finding P13-01).
The whole integration is that one dependency, added to `require` (not `require-dev`, since the images install with
`--no-dev`). Nothing else changed in the application: the mails are the same queued mailables
(`SendQueuedMailUnlessModelGone`), on the same worker (`--tries=5 --backoff=10,30,60,120`), through the same layout.

| Setting | Value for Resend |
|---|---|
| `MAIL_MAILER` | `resend` |
| `RESEND_API_KEY` | the key (a key restricted to sending is enough for sending) |
| `MAIL_FROM_ADDRESS` | an address on a domain verified in Resend |
| `MAIL_FROM_NAME` | `"${APP_NAME}"`, as before |
| `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD` | not used; leave empty |

The development and CI stacks keep `MAIL_MAILER=smtp` with `MAIL_HOST=mailpit`. Operator documentation: `docs/deployment/README.md`,
"Sending mail", and the mail block of `.env.production.example`.

Environment: `scripts/mail-delivery-test.sh` builds the production images from this working copy (based on `6014c9a`),
installs them into a clean directory with `install.sh`, as P-7 does, and starts the production stack (`docker-compose.yaml`
plus `compose.prod.yaml`, project `nusszopf-mail-delivery`) with the Redis queue, the production worker command and no
Mailpit. `APP_URL` is `http://127.0.0.1:18113`, so links in the received mails work on the maintainer's machine, while
the stack ran (section 5).

## 3. Send matrix and delivery result

`scripts/mail-delivery-send.php` calls the application's own entry points below the screens: `Mail::send(new
WelcomeMail)` as the registration does, `User::sendEmailVerificationNotification()`, `Password::sendResetLink()` (the
broker the "forgot password" form uses), the private `LoginRegister::sendBlockedAccountNotice()` by reflection,
`Mail::send(new ContactMail)` as `ProjectDetail` does, `Newsletter::subscribe()` and `Newsletter::requestUnsubscribe()`.
The Livewire forms in front of these calls are covered by the Playwright suite on the production images against Mailpit (P-7).
One run, 2026-09-26, 16:21 UTC.

| # | Mail type | Class | Subject | Queued, delivered to the provider by the production worker | Provider status |
|---|---|---|---|---|---|
| 1 | Welcome (registration) | `WelcomeMail` | Willkommen beim Nusszopf! | Yes, 305 ms | not readable (section 10) |
| 2 | E-mail verification | `VerifyEmailMail` | Nusszopf – Bestätige deine E-Mail-Adresse | Yes, 384 ms | not readable |
| 3 | Password reset link | `ChangePasswordMail` | Nusszopf – Neues Passwort erstellen | Yes, 198 ms | not readable |
| 4 | Account lockout / blocked IP | `BlockedAccountMail` | Nusszopf – IP-Adresse blockiert | Yes, 177 ms | not readable |
| 5 | Contact to a project owner (hostile content) | `ContactMail` | Nusszopf – Kontaktanfrage | Yes, 193 ms | not readable |
| 6 | Newsletter double opt-in | `NewsletterSubscribeMail` | Nussiger Newsletter – Anmeldebestätigung | Yes, 175 ms | not readable |
| 7 | Newsletter unsubscribe confirmation | `NewsletterUnsubscribeMail` | Nussiger Newsletter – Abmeldebestätigung | Yes, 294 ms | not readable |

"Delivered to the provider" means the job finished without an exception, so the Resend API accepted the message. After
the run the queue was empty (nothing waiting, reserved or delayed) and `failed_jobs` had 0 rows. The Resend rate limit
(a few requests per second) was not reached by seven messages in about a second; it was not tested beyond that.

Mail types in `docs/email/README.md` that the application does not send, and so were not tested:

- the breached-password alert (item 4): deferred by register C2, not built;
- a "password was changed" notification: no historical template and no decision asks for one;
- the newsletter issues "Nussig No. 1–3" (item 8) and the support letterhead (item 9): not application-triggered, scope
  Unknown, nothing to send.

## 4. What the messages look like on the wire

`scripts/mail-delivery-inspect.php` builds every mail like the application does, hands it to the in-memory `array`
mailer, and reads the message that reaches the transport. It sends nothing. Results, identical for all seven unless noted:

- **From:** `"Nusszopf" <MAIL_FROM_ADDRESS>` (the configured name and address). **To:** the one recipient.
- **Reply-To:** only the contact mail has one, the visitor's address (BUG-005, as before); the six others have none.
- **Subjects:** as in the table above, all with the en dash of the historical templates.
- **Parts:** HTML only. The application sets no plain-text alternative on any mail (finding P13-03, open).
- **Links:** every link starts with `APP_URL` (SEC-01), including the signed verification and unblock links, the
  reset link, the newsletter confirmation links, the footer's vCard and privacy links. The footer's Instagram link and
  `mailto:` (`NUSSZOPF_CONTACT_EMAIL`, else `MAIL_FROM_ADDRESS`) are unchanged.
- **Escaping (contact mail, the only one showing visitor text):** `<script>` and `<img onerror>` in the project and
  request titles, `<b>`, an injected `</p><a href="https://evil.example/…">` and `{{ 7*7 }}` / `{!! 8*8 !!}` in the
  message all reach the HTML as text (`&lt;script&gt;` …); no raw tag from the input, no injected anchor, and the
  template syntax is shown as typed. The account name is hostile too (`<b>P13 "Nuss" & Zopf</b>`); no template prints it.
- **Branding:** the shared layout, with the logo inlined as SVG (no external request), as before.

## 5. Proton Mail: manual inspection

The seven messages were sent at 16:21 UTC (18:21 in Germany) to the test mailbox.

**Result (the maintainer, 2026-09-26): all seven messages inspected in Proton Mail; delivery, rendering, branding,
links and the relevant message behaviour were correct; no observations and no defects.** This is the maintainer's
statement; the checklist below is what was asked of them, and no per-item values (header results, the presence of a
plain-text part) were reported back, so none are recorded here as observed.

Do this once in the mailbox (`More` → `View headers`, or `View source`, on any one message, for the header checks;
the same headers apply to all seven):

| Check | Expected |
|---|---|
| Sender shown | `Nusszopf` with the address from `MAIL_FROM_ADDRESS`, not a spam warning, not "via …" |
| `Authentication-Results` | `spf=pass`, `dkim=pass`, `dmarc=pass` (record the `header.d=` and `smtp.mailfrom=` domains) |
| Folder | Inbox, not Spam |
| Structure in the source | Whether there is a `text/plain` part next to `text/html` (`multipart/alternative`). Record the answer either way |

Then, for each message, look at the rendered mail (dark and light theme of the client if you use both):

| # | Message | Look at | Click |
|---|---|---|---|
| 1 | Willkommen beim Nusszopf! | Logo at the top; heading, both paragraphs, the orange "Falls Du diese Anfrage nicht gestellt hast…" line with a working `mailto`; footer with three icons, "Nusszopf als Kontakt speichern", "Datenschutzerklärung"; colours (grey page, white card, grey footer band); umlauts intact | Footer links: the privacy page opens; the vCard downloads |
| 2 | Bestätige deine E-Mail-Adresse | The button is a grey outlined pill; the rest as above | The button: the app confirms the address |
| 3 | Neues Passwort erstellen | Button "Neues Passwort erstellen" | The button: the reset form opens with the address filled in |
| 4 | IP-Adresse blockiert | Heading "Was in Nusszopfs Namen geht hier vor?", the IP `203.0.113.7` in bold, button "Das bin ich!" | The button: answers as the unblock page (a 403 here is a finding) |
| 5 | Kontaktanfrage | **Escaping.** Title reads literally `<script>alert("p13-title")</script> & "Quoted" 'Single'` `/` `<img src=x onerror=alert("p13-request")>`; the message box shows `<b>fett?</b>` as text (not bold), the `</p><a href=…>Klick</a>` as text, `{{ 7*7 }}` and `{!! 8*8 !!}` as typed, "Umlaute äöüß 🌰"; no alert, no link named "Klick" | **Reply-To:** press "Reply": the address filled in is `visitor@example.org`, not the sender |
| 6 | Newsletter – Anmeldebestätigung | Heading with soft hyphens ("Der News­letter ist zum Grei­fen nah!"), button "E-Mail-Adresse bestätigen" | The button: the confirmation page |
| 7 | Newsletter – Abmeldebestätigung | Heading "Der Nusszopf liebt dich sowieso!", button "Abmelden bestätigen" | The button: the unsubscribe confirmation |

Links pointed at `http://127.0.0.1:18113`, which worked on the maintainer's machine while the stack ran; the stack has been
removed since. Mail clients
that rewrite or proxy links (Proton warns before opening one) are expected to show their usual notice.
The Resend dashboard's delivery events were not reported and could not be read by the scripts (the key is restricted to
sending); the maintainer's inspection of all seven received messages is the delivery evidence.

## 6. DNS and header evidence

Observed with `dig` on 2026-09-26, for the domain the mails were sent from (`<domain>` below):

| Record | Observed |
|---|---|
| `TXT <domain>` | `v=spf1 include:_spf.protonmail.ch ~all` (the domain's mailbox provider, not Resend) and a mailbox-provider verification token |
| `TXT resend._domainkey.<domain>` | a DKIM public key, RSA, 1024 bit (`p=MIGfMA0GCSqGSIb3DQEBAQUAA4GN…`) |
| `MX send.<domain>` | `10 feedback-smtp.eu-west-1.amazonses.com` |
| `TXT send.<domain>` | `v=spf1 include:amazonses.com ~all` |
| `TXT _dmarc.<domain>` | `v=DMARC1; p=quarantine` (no `rua`, so no reports) |

- **Observed:** the records above exist. Resend rejects a sender on a domain that is not verified in the account
  (section 7), so the provider does require verification.
- **Not observed:** that a receiving server accepted the DKIM signature, SPF and DMARC: that needs the received
  headers (section 5, first table). **Provider-reported** verification status was not read (the key is restricted to
  sending). **Not verified:** which records Resend's dashboard asked for (the dashboard was not inspected), and that
  nothing else is needed for other providers. `docs/deployment/README.md` therefore words the DNS list as "what was
  found on this setup", not as a requirement.
- By the records, DMARC alignment should come from DKIM (the signing domain is the sender's) and SPF passes on the
  `send.` return-path subdomain, which is a subdomain of the sender's domain: an inference until the headers are read.

## 7. Provider failure and `failed_jobs`

One controlled failure with the real integration and the real key, no mail sent: the sender was set to an address at a
domain the account has not verified (`example.org`) and one welcome mail queued on the production stack.

- Resend refused it (HTTP error, `The example.org domain is not verified. Please, add and verify your domain on
  https://resend.com/domains`). The worker tried five times, 10 s, 30 s, 60 s and 120 s apart (16:22:34, 16:22:46,
  16:23:16, 16:24:17, 16:26:17), and then the job was in `failed_jobs` with that message.
- `php artisan nusszopf:health` showed `failed_jobs FAILED — 1 failed job — see php artisan queue:failed; run queue:retry
  all to try them again, or queue:flush to forget them`, and the other five checks `ok`. This is the P-12 path, unchanged.
- The submitting action is not affected (mails are queued). Then the sender was put back and the job flushed.

Not tested: Resend rate limiting (HTTP 429), an invalid or revoked key, a Resend outage. All of these reach the worker as
the same exception type and take the same retry path; that is a reasoning, not a measurement.

## 8. Findings and fixes

| ID | Finding | Result |
|---|---|---|
| P13-01 | `MAIL_MAILER=resend` could not send: the mailer and the key were configured, but `resend/resend-php` was not installed, so the image had no transport | **Fixed.** Dependency added to `require`. Regression tests `tests/Feature/Mail/ResendMailerTest.php` (the package is a production dependency; the mailer builds from `RESEND_API_KEY`). Verified by real delivery |
| P13-02 | Operator documentation named only an SMTP relay; `.env.production.example` did not mention the Resend option, the recreate-not-restart rule, or that a sender on an unverified domain is refused | **Fixed.** `docs/deployment/README.md` "Sending mail", `.env.production.example`, `docs/testing/README.md` |
| P13-03 | The application sends **HTML-only** mails: no `text/plain` alternative on any of the seven | **Accepted by the maintainer (2026-09-26) as the intended Nusszopf mail format; not a defect, no change.** Do not add text parts. Recorded in `docs/rewrite/decisions-register.md` ("Mail format") and `docs/email/README.md` |
| P13-04 | With a `no-reply@` sender and no `NUSSZOPF_CONTACT_EMAIL`, every footer, the error page and the contact card say "write to the no-reply address" | **Documented**, not a code change: it is the existing, decided fallback (`docs/deployment/README.md`, "Your identity"); the new "Sending mail" section says to set the variable |

None of the four is a historical defect, so `docs/rewrite/bugs.md` and `intentional-changes.md` are unchanged.

## 9. Client status (roadmap: Gmail, Outlook, Apple Mail)

| Client | Status |
|---|---|
| Gmail | **Not verified.** No Gmail mailbox was available |
| Outlook | **Not verified.** No Outlook mailbox was available. Outlook desktop's Word-based renderer is the client most likely to differ (rounded buttons, inlined SVG) |
| Apple Mail | **Not verified.** No Apple device was available |
| Proton Mail | **Verified by the maintainer**, all seven messages (section 5). Additional evidence, **not** a replacement for the three |

Consequence: the roadmap's exit criterion names Gmail, Outlook and Apple Mail, so it is **not fully met**. The
maintainer closed P-13 anyway on 2026-09-26, keeping the three checks recorded as unverified and deferred, as P-5
deferred the real-device pass. They are **not waived**: P-16 (release candidate testing) must receive the seven
messages in a Gmail, an Outlook (desktop and web) and an Apple Mail mailbox and record the result here, including
whether the inline SVG logo shows.

A risk worth knowing for that pass: the logo is an inline `<svg>`, and email clients are commonly reported not to render
inline SVG (Gmail and Outlook in particular). This was not tested here. If the header shows nothing in a client, that
is a finding for this phase (the historical logo was a hosted image, `docs/email/README.md`).

## 10. Limitations and deferred checks

- Gmail, Outlook, Apple Mail: unverified, deferred to P-16 (section 9).
- A generic SMTP relay with real TLS: not tested; Resend was used through its API. The SMTP path is still verified only
  against Mailpit.
- Provider-side delivery events (delivered, bounced, complained): the key is restricted to sending, so they were not
  read by the scripts. A full-access key would let them check.
- DKIM/SPF/DMARC results as a receiver saw them: not recorded (the maintainer reported no header values), so the
  alignment reasoning in section 6 stays an inference.
- The message identifiers Resend returned were not recorded, so a message cannot be matched to the dashboard except by
  time (16:21 UTC) and subject.
- Rate limits (429), key revocation, provider outage, greylisting: not measured (section 7). The queue may deliver a
  mail twice after a worker crash (P-12); with a real provider that is a second mail.
- The stack ran on a workstation with a local `APP_URL`; the links in the received mails are not the public HTTPS
  addresses an operator would have. Their construction from `APP_URL` is verified (section 4), and P-8 covers the proxy.

## 11. Reproducing

```
P13_RECIPIENT=you@example.org sh scripts/mail-delivery-test.sh          # real mail: seven messages
docker compose exec -T php-fpm php < scripts/mail-delivery-inspect.php   # what goes on the wire; sends nothing
```

Closed by the maintainer 2026-09-26. Not started: P-14 and later.
