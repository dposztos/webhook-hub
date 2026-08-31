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

### requirements.txt, without rebuilding

Put a `requirements.txt` next to your scripts and switch it on:

```dotenv
WEBHOOK_SCRIPTS_REQUIREMENTS=true
```

```
scripts/requirements.txt →  six==1.16.0
                            openpyxl==3.1.5
```

On start the container installs it into a virtualenv (kept on its own volume,
built `--system-site-packages` so `pyodbc` and the rest of the image stay
importable) and runs every script with that interpreter. A new library is then a
line in the file plus `docker compose restart app`. Unchanged file, no reinstall.
A failed install does not keep the app down: webhooks are still captured, what
was installed before keeps working, and the rule editor shows pip's error.

Pin versions. The point of a file is that two containers end up the same.

**Why not install whatever a script imports, automatically?** Because the import
name is not the package name — `import yaml` is `pyyaml`, `import cv2` is
`opencv-python` — so a guess either misses or installs the package that someone
registered under the guessable name. It would also run pip during a webhook, and
leave two containers with different versions of the same library.

### A derived image, for a fixed set

When the set of libraries belongs to the deployment rather than to the scripts,
build them in; `docker/prod/Dockerfile.scripts` is a working template with both
routes:

- **Debian packages** (`python3-requests`, `python3-openpyxl`, …) — prebuilt, no
  compiler in the image. `apt-cache search '^python3-'` lists them.
- **pip into a virtualenv**, for anything Debian does not package. Build it with
  `--system-site-packages` so `pyodbc` and the AS/400 helper still import, and
  point `WEBHOOK_PYTHON_BIN` at `/opt/venv/bin/python3`.

The DB2 variant already carries `pyodbc`.

## Chaining steps

The actions of one rule run in order, and each one's result is put into the
context under `steps`, so a later action can use what an earlier one produced. A
script that queries a database and an e-mail that reports the answer are two
steps, not one script that also sends mail:

```twig
{{ steps.query.output.total|money }}
{% for row in steps.query.output.rows %}
  {{ row.name }}
{% endfor %}
```

A step is addressed by its **action name**, lowercased and underscored
(`Lekérdezés` → `lekerdezes`), or by `step_1`, `step_2`… when it has no name. Two
steps with the same name get a `_2` suffix rather than silently overwriting each
other. Each step carries:

| field | what it holds |
| --- | --- |
| `output` | the JSON the script printed on stdout, parsed; `null` if it printed something else |
| `stdout` | the raw output, truncated at the output limit |
| `status` | `success`, `failed` or `skipped` |
| `exit_code`, `error` | for a step that failed |

`steps` starts empty for every rule, so a rule never sees another rule's steps —
what a rule does stays reproducible on its own.

**A failed step does not stop the ones after it** by default: an e-mail that
follows a broken query would go out carrying nothing. Tick *only if the previous
step succeeded* on the later action to prevent that; it is then recorded as
skipped, with the reason.

Testing a single action from the editor renders `steps` as empty — there are no
earlier steps in a one-action dry run. Send a webhook to the endpoint to see the
whole chain.

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
