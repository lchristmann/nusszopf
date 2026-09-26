# Security Policy

## Reporting a vulnerability

Please report a vulnerability **privately**, not in a public issue or pull request:

1. Open the repository's **Security** tab and choose **Report a vulnerability**
   ([direct link](https://github.com/lchristmann/nusszopf/security/advisories/new)).
2. Describe what you found, how to reproduce it, and what it lets an attacker do. The version (`NUSSZOPF_VERSION` in
   your `.env`, or the commit) and whether it is the production or the development stack help.

Nusszopf is maintained by one person, so there is no guaranteed response time. Reports are read and answered as soon as
possible. A confirmed vulnerability is fixed in a new release and disclosed in a GitHub security advisory and in
`CHANGELOG.md` (under **Security**) once the fix can be installed. Please give the maintainer a reasonable time to do
that before you publish details; you are credited in the advisory if you wish.

## Supported versions

Only the **latest release** receives security fixes. There is no release yet (`docs/release/versioning.md`); until
there is one, the `main` branch is what is supported. Operators should stay on the latest release
(`docs/deployment/operations.md`, "Upgrades").

## What is in scope

The application in this repository, the Docker images built from it (`ghcr.io/lchristmann/nusszopf-php-fpm` and
`ghcr.io/lchristmann/nusszopf-web`), and the operator files it ships (`docker-compose.yaml`, `install.sh`,
`.env.production.example`, the backup and restore procedures). Examples: access to another user's private project or
request, an authorization bypass, injection, account takeover, a secret exposed by default.

Out of scope: a vulnerability in a third-party component itself (report it upstream; Dependabot and the weekly
`Security` workflow tell us when an advisory affects a locked version), a problem that needs an already-compromised
server or an operator's misconfiguration that the documentation warns against, and denial of service by sheer volume.

## What the project already does

How authorization, secrets, rate limits, headers and the deployment are handled is described in
`docs/security/README.md` (requirements) and `docs/release/parity/P-04-security.md` (the security review and its
findings). The operator's side (reverse proxy and TLS, secrets, backups, upgrades) is in `docs/deployment/README.md`
and `docs/deployment/operations.md`.
