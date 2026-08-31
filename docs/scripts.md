# Script actions

A rule can run a Python script when a webhook arrives, alongside (or instead of)
sending mail. The captured message goes to the script on stdin as JSON; the exit
code decides whether the action counts as successful, and stdout/stderr are kept
with the run.

This is code execution on your server, driven from the admin UI, so it is off
until you switch it on.

## Turning it on

```dotenv
WEBHOOK_SCRIPTS_ENABLED=true
WEBHOOK_SCRIPTS_HOST_DIR=./scripts      # the folder on the host
WEBHOOK_SCRIPTS_DIR=/app/scripts        # where it is mounted in the container
WEBHOOK_PYTHON_BIN=/usr/bin/python3
```

`docker compose up -d` mounts that folder **read-only** at `/app/scripts`. Every
`.py` file under it (up to four levels deep) shows up in the rule editor's
script list. Nothing outside the folder can be run: relative paths that climb
out of it, and symlinks that point out of it, are refused.

Inline code — a script typed into the rule itself rather than picked from the
folder — needs a second switch, because with it on, anyone who can sign in can
run arbitrary code:

```dotenv
WEBHOOK_SCRIPTS_ALLOW_INLINE=true
```

Inline code runs from a temporary file that is deleted after the run, with the
script folder on `PYTHONPATH`, so shared helpers stay importable.

## What a script receives

**stdin** — the whole message as JSON by default, with the same variables the
e-mail templates use:

```json
{
  "json":     { "event": "order.created", "order": { "id": 42 } },
  "body":     "…raw request body…",
  "headers":  { "content-type": "application/json" },
  "query":    { "source": "shop" },
  "meta":     { "method": "POST", "ip": "10.0.0.5", "received_at_local": "…" },
  "endpoint": { "name": "Orders", "slug": "orders" },
  "group":    { "name": "ACME" }
}
```

A rule can send a rendered template instead, or nothing at all.

**Arguments** are a Twig template, split the way a shell would split them —
quotes group, whitespace separates — but no shell is involved, so nothing in a
payload can turn into a command:

```
--order "{{ json.order.id }}" --total {{ json.order.total }}
```

**Environment.** The container's own variables are visible to the script, which
is where credentials belong ([AS/400 example](as400.md)). A rule may add extra
variables, but only ones named `WEBHOOK_*` — enough for passing a value along,
not enough to reach `LD_PRELOAD` or `PATH`.

## What counts as success

| Exit code | Result |
| --- | --- |
| `0` | success |
| anything else | failed, with the last lines of stderr as the error |
| killed at the timeout | failed |

The default timeout is `WEBHOOK_SCRIPT_TIMEOUT` (30s), a rule may ask for more
up to `WEBHOOK_SCRIPT_MAX_TIMEOUT` (300s). Output is kept up to
`WEBHOOK_SCRIPT_MAX_OUTPUT` bytes per run and truncated beyond that, so a chatty
script cannot fill the database. If stdout happens to be JSON, it is parsed and
stored as structured data on the run.

```python
import json, sys

payload = json.load(sys.stdin)
order = payload["json"]["order"]

print(json.dumps({"queued": order["id"]}))
sys.exit(0)
```

`scripts/example.py` in the repository is a working starting point.

## Python libraries

The image ships a bare `python3`: the standard library (`json`, `urllib`, `csv`,
`sqlite3`, `smtplib`) and nothing else. There is no `pip` in it, and Debian marks
the system interpreter externally managed, so nothing can be installed into a
running container — by design, since a container is meant to be replaceable.

Add libraries by building a small image on top of the published one;
`docker/prod/Dockerfile.scripts` is a working template with both routes:

- **Debian packages** (`python3-requests`, `python3-openpyxl`, …) — prebuilt, no
  compiler in the image. `apt-cache search '^python3-'` lists them.
- **pip into a virtualenv**, for anything Debian does not package. Build it with
  `--system-site-packages` so `pyodbc` and the AS/400 helper still import, and
  point `WEBHOOK_PYTHON_BIN` at `/opt/venv/bin/python3`.

The DB2 variant already carries `pyodbc`.

## Testing a rule

The editor has **Dry run**, which resolves the script and the arguments without
starting anything, and **Run now**, which really executes it against the latest
captured message. Both show the exit code, stdout and stderr. Every run from a
real webhook is recorded on the message, with the same detail.

## Things worth deciding before you turn this on

- The script runs as the web application's user, inside the app container, with
  whatever network the container has. It can reach your internal services.
- Anyone who can sign in to the UI can attach a script action to a rule. There
  are no per-user permissions yet — every account is an admin.
- With inline code enabled, the UI is a code editor that runs on the server.
  Leaving it off and shipping reviewed files into the script folder is the more
  conservative arrangement.
