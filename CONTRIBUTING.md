# Contributing

Thanks for looking. This is a spare-time project, so replies may take a few
days — that is not disinterest.

## Adding a language

This is the easiest useful contribution, and it needs no PHP or JavaScript.

There are two catalogs per language:

| File | Used by | Format |
| --- | --- | --- |
| `lang/<code>.json` | the Vue UI | flat JSON, `{placeholders}` |
| `lang/<code>/*.php` | server messages (console, validation, e-mail) | Laravel arrays, `:placeholders` |

To add German, for example:

```bash
cp lang/en.json lang/de.json
cp -r lang/en lang/de
```

Then translate the **values** — never the keys — and set `"_name": "Deutsch"` in
`lang/de.json`. That `_name` is what the language switcher shows, in the
language's own spelling.

Nothing else is needed. `resources/js/i18n.js` discovers catalogs by globbing
`lang/*.json` at build time, so the new language appears in the switcher on the
next `npm run build`. Laravel finds the PHP files on its own.

Two details worth knowing:

- **Placeholders must survive.** `"{count} unread message"` may become
  `"{count} ungelesene Nachricht"`, but the `{count}` has to stay, spelled the
  same way.
- **Plurals use a pipe.** `"{count} message|{count} messages"` is singular
  before the `|`, plural after. A language that does not inflect after a numeral
  simply repeats the same text on both sides — that is what `lang/hu.json` does.

`tests/Unit/LanguageCatalogTest.php` checks all of this: missing keys, extra
keys, placeholder drift, and the `_name`. Run `./php php artisan test` before
opening the PR and it will tell you exactly what is off.

## Development setup

No local PHP or Node needed beyond npm — everything runs in containers:

```bash
docker compose -f docker/dev/compose.yml up -d   # Postgres + app on :8090 + queue worker
cp .env.example .env                             # set APP_ENV=local, APP_DEBUG=true for dev
./php php artisan key:generate
./php php artisan migrate
./php php artisan db:seed --class=DemoSeeder     # sample group, endpoint and rule
./php php artisan webhook:admin you@example.com --password=changeme12
npm install && npm run dev
```

`./php` is a thin wrapper that runs a command inside the app container, so
`./php php artisan …` works without a PHP install on the host.

## Before opening a pull request

```bash
./php php artisan test     # 43 tests, needs the dev Postgres running
npm run build              # must be clean
./php ./vendor/bin/pint    # code style (Laravel Pint)
```

Please keep to what is already there:

- **English** for code, comments, commit messages and identifiers. User-facing
  text goes through `t()` / `__()` and lands in `lang/`, never inline.
- **No new runtime dependencies** without a reason in the PR description. The
  frontend deliberately has none beyond Vue and Tailwind; the i18n layer is
  ~60 lines rather than a library.
- Tests for behaviour changes. The suite runs against real Postgres because the
  schema uses `jsonb` and a GIN index.

## Reporting bugs

Include the version or image tag, what you sent, what you expected and what
happened. If it involves a captured message, the **Headers** tab and the raw
body are usually what is needed — with anything sensitive removed.

Security problems do **not** belong in a public issue; see [SECURITY.md](SECURITY.md).

## Scope

Webhook Hub is a receiving and inspection tool for webhooks, self-hosted by
people who trust each other. Things that fit: more action types, better
inspection, more protocols in, translations, deployment ergonomics. Things that
do not: multi-tenancy with per-user permissions, becoming a general message
broker, or a hosted SaaS mode.
