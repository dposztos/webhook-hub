# Webhook Hub

*[Magyar leírás](README.hu.md)*

A self-hosted webhook receiver that keeps your messages, organises them in a
tree, and can act on what arrives.

It began as a fork of the MIT-licensed open-source [webhook.site](https://github.com/fredsted/webhook.site),
but it is its own application now: Laravel 12 + PostgreSQL + Vue 3. The original
state of the fork is preserved on the `upstream-master` branch.

What the open-source original could not do, and this can:

- **A group hierarchy.** A tree of arbitrary depth (`Customers / ACME Ltd. /
  Orders`), with any number of URLs per group. Every URL is a separate endpoint
  with its own settings.
- **Every message is kept.** No 500-request cap per URL and no 7-day expiry.
  Retention can be limited per endpoint if you do want something cleaned up.
- **Unread tracking.** A new message arrives unread — blue marker in the list, a
  counter in the tree that also sums onto the groups. Opening it marks it read,
  and there is both "mark all read" and "put back to unread".
- **Replay with anything.** Every message comes with a ready-to-run command for
  curl, PowerShell, HTTPie, Python, JavaScript and raw HTTP — aimed at the
  endpoint's current address, without the proxy headers.
- **Rules and actions.** Nestable AND/OR conditions over the parsed body,
  headers and query parameters, with actions bound to them. Currently: sending
  an e-mail from an HTML template with variables referencing the captured data.

## Try it

```bash
git clone https://github.com/cactuska/webhook-hub.git
cd webhook-hub
cp .env.example .env
# put the printed key into APP_KEY, and set DB_PASSWORD, ADMIN_EMAIL, ADMIN_PASSWORD
docker compose run --rm app php artisan key:generate --show
docker compose up -d
```

The UI is then on <http://localhost:8080>. Sign in with the `ADMIN_EMAIL` /
`ADMIN_PASSWORD` from your `.env` — there is no public sign-up.

Prebuilt images: `ghcr.io/cactuska/webhook-hub:latest` and
`dposztos/webhook-hub:latest`.

## How the URLs are built

```
https://<host>/u/<group>/<subgroup>/<endpoint>/<secret>[/<anything>]
               └──────── path in the tree ────────┘  └ 12 chars, rotatable
```

Example: `https://webhooks.example.com/u/customers/acme/orders/k7f3q9x2mnpq`

- The path shows which customer's which process is sending — but without the
  secret it cannot be guessed.
- The secret is rotatable in the UI (**Settings → New secret**); the old URL
  starts returning 404 immediately.
- Anything can be appended (it gets stored), and a trailing segment such as
  `/404` overrides the returned status code.
- Renaming does **not** change the URL: the path comes from the slug, which is
  fixed at creation.

## Rules

A rule belongs either to an endpoint or to a group. **A rule on a group runs for
every endpoint below it** — so "notify me on any failed status from ACME" is
written once. Rules run in priority order; turning on `stop_processing` makes a
match end the chain.

Condition sources: `json` (the parsed body — JSON, form-urlencoded and multipart
alike), `header`, `query`, `meta` (method, ip, url, size, content_type…), `body`
(raw text).

Field references use dot notation: `order.items.0.sku`, or `order.items.*.sku`
for every element.

Operators: `equals`, `not_equals`, `contains`, `not_contains`, `starts_with`,
`ends_with`, `regex`, `not_regex`, `gt`, `gte`, `lt`, `lte`, `in`, `not_in`,
`exists`, `not_exists`, `is_empty`, `is_not_empty`, `is_true`, `is_false`.
Numbers compare as numbers (including the `"1 234"` form), dates as timestamps.

The **Try it on the latest message** button in the editor shows whether the rule
would match, and reports the outcome of each condition separately.

## E-mail templates

The subject and the body are [Twig](https://twig.symfony.com/) templates run in
a **sandbox**: only allow-listed tags and filters, no function or method calls,
and the captured data is HTML-escaped on the way in.

```twig
<style>.total { font-size: 20px; font-weight: 700; color: #2563eb }</style>

<h2>Thanks for your order, {{ json.customer.name|default('there') }}!</h2>
<p>Reference: <strong>{{ json.order.id }}</strong></p>
<p class="total">{{ json.order.total|money }}</p>

{% for item in json.order.items %}
  <p>{{ item.sku }} — {{ item.qty }} pcs</p>
{% endfor %}

{{ json|table }}
<p>Received: {{ meta.received_at_local }}</p>
```

Available variables: `json`, `body`, `headers`, `query`, `meta`, `endpoint`,
`group`. Custom filters: `money` (24990 → "24,990", configurable), `table`
(array → HTML table), `json_pretty`, `local_date`. The `<style>` block is
inlined before sending so mail clients render it correctly.

The recipient can be a template too: `{{ json.customer.email }}, ops@example.com`.
Invalid addresses are dropped; if none survive, the action is logged as failed
(the captured message is still kept).

## Configuration

| Variable | Meaning |
| --- | --- |
| `APP_URL` | Webhook URLs are generated with this host |
| `APP_LOCALE` | UI and e-mail language (`en`, `hu`) |
| `SESSION_SECURE` | `true` behind HTTPS, `false` for plain HTTP (otherwise login silently fails) |
| `WEBHOOK_MAX_BODY_BYTES` | Bodies larger than this are stored truncated, with a marker |
| `WEBHOOK_INGEST_RATE_LIMIT` | Incoming requests per minute per IP (0 = unlimited) |
| `WEBHOOK_RETENTION_DAYS` | Global default retention; empty = forever |
| `WEBHOOK_ALLOWED_RECIPIENTS` | If set, actions may only mail addresses matching these patterns |
| `ADMIN_EMAIL`, `ADMIN_PASSWORD` | Admin created (or updated) on start |

See [`.env.example`](.env.example) for the full list.

## Deployment

The `Dockerfile` has three stages: Vite build → composer install → runtime
(nginx + php-fpm under supervisord, which also runs the queue worker and the
scheduler). Migrations run on start, and the admin user is created or updated
when `ADMIN_EMAIL`/`ADMIN_PASSWORD` are set.

`docker/prod/stack.yml` is the same thing as a Portainer stack.

## Development

No local PHP needed — everything runs in containers:

```bash
docker compose -f docker/dev/compose.yml up -d      # Postgres + app (:8090) + queue worker
./php php artisan migrate                            # artisan inside the container
./php php artisan db:seed --class=DemoSeeder         # sample group + endpoint + rule
./php php artisan webhook:admin you@example.com --password=…
npm install && npm run build                         # frontend
./php php artisan test                               # tests
```

## Contributing

Bug reports, ideas and pull requests are welcome — see
[CONTRIBUTING.md](CONTRIBUTING.md). **Translations especially**: adding a
language means copying `lang/en.json` and `lang/en/`, translating the values,
and nothing else.

Security issues go through GitHub's private vulnerability reporting rather than
a public issue; [SECURITY.md](SECURITY.md) describes the scope and what is
explicitly out of it.

## Licence

MIT, keeping the original webhook.site licence — see [LICENSE](LICENSE).
