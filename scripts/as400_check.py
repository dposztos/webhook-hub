#!/usr/bin/env python3
"""Connectivity check for the AS/400.

Run it from the rule editor ("Run now") or from a shell:

    docker compose exec app python3 /app/scripts/as400_check.py

It prints one JSON object: on success the server version and the current user,
on failure the ODBC error. Nothing is written to the AS/400.
"""

import json
import sys

import as400


def main() -> int:
    try:
        with as400.connect() as connection:
            cursor = connection.cursor()
            cursor.execute(
                "SELECT CURRENT_SERVER, CURRENT_USER, CURRENT_TIMESTAMP FROM SYSIBM.SYSDUMMY1"
            )
            server, user, now = cursor.fetchone()
    except Exception as error:  # noqa: BLE001 — the message is the whole point
        print(json.dumps({"ok": False, "error": str(error)}, ensure_ascii=False))
        return 1

    print(json.dumps({
        "ok": True,
        "server": str(server).strip(),
        "user": str(user).strip(),
        "server_time": str(now),
    }, ensure_ascii=False))

    return 0


if __name__ == "__main__":
    sys.exit(main())
