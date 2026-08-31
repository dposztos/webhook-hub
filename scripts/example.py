#!/usr/bin/env python3
"""Example script action.

The captured webhook arrives as JSON on stdin, with the same variables the
e-mail templates use: json, headers, query, meta, endpoint, group.

Exit code 0 marks the action successful; anything else marks it failed and the
last lines of stderr end up in the run log. Whatever the script prints on stdout
is kept with the run, and parsed if it happens to be JSON.
"""

import json
import sys


def main() -> int:
    raw = sys.stdin.read()
    payload = json.loads(raw) if raw.strip() else {}

    body = payload.get("json") or {}
    meta = payload.get("meta") or {}

    print(json.dumps({
        "endpoint": (payload.get("endpoint") or {}).get("name"),
        "received_at": meta.get("received_at_local"),
        "event": body.get("event"),
        "keys": sorted(body.keys())[:20],
    }, ensure_ascii=False))

    return 0


if __name__ == "__main__":
    sys.exit(main())
