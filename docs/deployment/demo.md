# Public demo mode

A demonstration instance (the project's own is <https://nusszopf.org>) lets anyone try the real application without
registering, using fictional data. It is **off by default**; an ordinary installation never needs it.

```sh
# .env
NUSSZOPF_DEMO=true
```

Restart the stack, then create the sample data once (the scheduler rebuilds it every hour afterwards):

```sh
docker compose exec php-fpm php artisan demo:reset
```

## What it does

- **One shared account, no password.** `demo:reset` creates the user `demo` (`demo@demo.example.org`) with five invented
  projects — four public, one private draft — and their requests (Gesuche) in every category. The account has no
  password, so the login form can never sign in as it; the only way in is the "Demo ausprobieren" button on Home
  (`POST /demo/login`, which answers 404 unless demo mode is on). A visitor already signed in with a real account is
  never switched to the demo account.
- **No personal data.** Everything is invented; the projects' contact address is `kontakt@example.org` (reserved for
  examples), and no project uses the "contact runs through Nusszopf" relay, so no message from the demo can reach a
  real mailbox.
- **Guarded against destructive changes.** Nobody can delete the demo account or change its avatar (`UserPolicy`), and
  its Profile explains this; subscribing its address to the newsletter is refused. Visitors *can* create, edit,
  publish and delete projects — that is the point of a demo — and share one account, so they see each other's changes.
- **Reset every hour.** `demo:reset` (scheduled hourly, only in demo mode) deletes the demo account and everything it
  owns through Eloquent, so the search index follows, and rebuilds it. It touches no other account. Anything a visitor
  did as `demo` disappears at the next full hour, and a visitor signed in at that moment is signed out. It refuses to
  run unless `NUSSZOPF_DEMO` is on. Run it by hand for an immediate reset.
- **No real address is collected.** In demo mode registration is off (the register tab explains and offers the demo
  button instead, and `register()` refuses too), Google sign-in answers 404, the newsletter sign-up is not shown on
  Home and refuses if reached, the demo account cannot subscribe, and the project contact form ("Über Nusszopf") sends
  no message. Forgotten-password and unsubscribe only act on addresses that already exist. With demo mode off, every one
  of these works as before. One residual: a visitor can type an address into a project they create as `demo` (a
  personal contact); it is shown publicly and deleted at the next hourly reset.
- **Home** shows a card with "Demo ausprobieren" and "Geführte Tour starten" above the how-to, and replaces its
  newsletter form with an explanation.
- **Guided tour.** Signed in as `demo`, a "Geführte Tour" button (bottom left) starts an eight-step tour over the real
  pages: Meine Projekte, starting a project, search and its filter, a project's detail and Gesuche, and the settings
  with the newsletter. It is a small non-modal overlay (`resources/js/tour.js`, no library): it highlights the actual
  element, the page stays usable, "Tour beenden" or Escape ends it at any step, and it works by keyboard and on phones.
  Progress is kept in `sessionStorage`. Its selectors are the `data-test` attributes of the pages it points at, so
  `tests/E2E/specs/demo/demo-tour.spec.ts` fails if a page change breaks a step.

## Operating notes

- Because registration is off in demo mode, the demo holds no visitor accounts; the reset touches only `demo`.
- The demo stays as public as any instance: moderate it, and give it its own legal pages (`docs/deployment/README.md`).
- Tests: `tests/Feature/Demo/DemoTest.php`, and the browser spec, which needs a stack started with demo mode:
  `E2E_DEMO=1 npx playwright test specs/demo` (`docs/testing/README.md`).
