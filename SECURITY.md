# Security Policy

## Supported versions

Security fixes land on `master` and in the `latest` container image. There are no
long-term support branches for older releases.

## Reporting a vulnerability

Please **do not open a public issue** for security problems.

Report privately through GitHub's
[private vulnerability reporting](https://docs.github.com/en/code-security/security-advisories/guidance-on-reporting-and-writing-information-about-vulnerabilities/privately-reporting-a-security-vulnerability)
on this repository (Security → Report a vulnerability). Expect a first response
within about a week; this is a spare-time project, so please be patient.

Please include what you need to reproduce it: affected version or image tag, the
request or configuration involved, and what an attacker gains.

## Scope and threat model

Webhook Hub is a **self-hosted webhook receiver and inspection tool**. It is meant
to run behind your own HTTPS termination, with the admin UI reachable only by
people you trust. It is not designed to be a hardened multi-tenant service or a
production message broker.

The following are in scope and worth reporting:

- Reading messages, endpoints or rules without authenticating.
- Escaping the Twig sandbox used by e-mail templates (function or method calls,
  filesystem or environment access).
- Stored XSS through a captured webhook payload rendered in the UI.
- Endpoint secret disclosure, or guessing an endpoint URL without its secret.
- Sending mail to a recipient that `WEBHOOK_ALLOWED_RECIPIENTS` should have blocked.

The following are known and accepted, and are **not** vulnerabilities:

- Anyone who knows an endpoint URL can post messages to it. That is the point of
  the product; the secret segment in the path is the access control.
- Captured payloads are stored unencrypted in PostgreSQL. Do not send secrets you
  cannot afford to have at rest, and set a retention limit if you do.
- The admin UI has no per-user permissions. Every logged-in user is an admin.
- Running the app on plain HTTP with `SESSION_SECURE=false` exposes the session
  cookie. Use HTTPS for anything but local testing.
