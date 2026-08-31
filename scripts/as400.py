"""Db2 for i (AS/400) connections for script actions.

Credentials come from the container's environment, not from the rule, so a
webhook rule can query the AS/400 without anyone pasting a password into the UI:

    AS400_HOST=ibmi.example.local
    AS400_USER=WEBHOOK
    AS400_PASSWORD=…
    AS400_LIBRARIES=MYLIB,OTHERLIB   # optional, first one is the default schema
    AS400_NAMING=0                   # 0 = SQL naming (LIB.FILE), 1 = system (LIB/FILE)
    AS400_COMMIT=0                   # 0 = *NONE, needed for non-journalled tables
    AS400_EXTRA=CCSID=1208           # anything else, appended verbatim

Requires the DB2-capable image (docker/prod/Dockerfile.db2), which carries the
IBM i Access ODBC driver and pyodbc.
"""

import os

import pyodbc

DRIVER = "IBM i Access ODBC Driver"


def connection_string(**overrides: str) -> str:
    """Builds the ODBC connection string from the environment."""
    parts = {
        "DRIVER": "{" + os.environ.get("AS400_DRIVER", DRIVER) + "}",
        "SYSTEM": os.environ.get("AS400_HOST", ""),
        "UID": os.environ.get("AS400_USER", ""),
        "PWD": os.environ.get("AS400_PASSWORD", ""),
        # *NONE. Db2 for i refuses to read a non-journalled table under any
        # other isolation level, which is the classic first-day surprise.
        "CMT": os.environ.get("AS400_COMMIT", "0"),
        "NAM": os.environ.get("AS400_NAMING", "0"),
        # UTF-8 in and out, so EBCDIC columns arrive as normal Python strings.
        "CCSID": os.environ.get("AS400_CCSID", "1208"),
        "LOGINTIMEOUT": os.environ.get("AS400_LOGIN_TIMEOUT", "15"),
    }

    libraries = os.environ.get("AS400_LIBRARIES", "").strip()

    if libraries:
        parts["DBQ"] = libraries.replace(",", " ")

    parts.update({key.upper(): value for key, value in overrides.items()})

    if not parts["SYSTEM"]:
        raise RuntimeError("AS400_HOST is not set — see docs/as400.md")

    rendered = ";".join(f"{key}={value}" for key, value in parts.items() if value != "")
    extra = os.environ.get("AS400_EXTRA", "").strip().strip(";")

    return f"{rendered};{extra};" if extra else f"{rendered};"


def connect(**overrides: str) -> "pyodbc.Connection":
    """Opens a connection with sane text handling for EBCDIC systems."""
    connection = pyodbc.connect(connection_string(**overrides), autocommit=True)

    connection.setdecoding(pyodbc.SQL_CHAR, encoding="utf-8")
    connection.setdecoding(pyodbc.SQL_WCHAR, encoding="utf-8")
    connection.setencoding(encoding="utf-8")

    return connection


def rows(sql: str, *params: object) -> list[dict]:
    """Runs a query and returns the rows as dictionaries."""
    with connect() as connection:
        cursor = connection.cursor()
        cursor.execute(sql, *params)
        columns = [column[0] for column in cursor.description]

        return [dict(zip(columns, row)) for row in cursor.fetchall()]
