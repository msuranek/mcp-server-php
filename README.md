# Secure Database MCP Server for PHP

[![CI](https://github.com/msuranek/mcp-server-php/actions/workflows/ci.yml/badge.svg)](https://github.com/msuranek/mcp-server-php/actions/workflows/ci.yml)
[![PHP 8.2+](https://img.shields.io/badge/PHP-8.2%2B-777BB4.svg)](https://www.php.net/)
[![MCP SDK](https://img.shields.io/badge/MCP-official%20PHP%20SDK-5A67D8.svg)](https://github.com/modelcontextprotocol/php-sdk)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](LICENSE)

A runnable reference implementation of a constrained, read-only database [Model Context Protocol](https://modelcontextprotocol.io/) server. It exposes a PostgreSQL database to MCP clients without accepting arbitrary SQL.

The project focuses on protocol interoperability, database authorization, bounded queries and auditability. It uses the [official MCP PHP SDK](https://github.com/modelcontextprotocol/php-sdk) for protocol handling and communicates with clients over STDIO. No model API key is required by the server.

## Capabilities

The server publishes one resource:

- `database://schema` — the allowed tables, columns, types and connection read-only state.

It publishes three read-only tools:

| Tool | Purpose |
| --- | --- |
| `list_tables` | Lists the exact table allowlist. |
| `describe_table` | Describes columns of one allowed table. |
| `query_records` | Executes a server-built, parameterized `SELECT`. |

`query_records` accepts structured fields, not SQL:

```json
{
  "table": "orders",
  "columns": ["status", "total_amount", "currency"],
  "filters": [
    {"column": "status", "operator": "eq", "value": "completed"}
  ],
  "limit": 20,
  "orderBy": "total_amount",
  "direction": "desc"
}
```

## Security boundaries

Security is enforced in layers:

1. PostgreSQL uses a dedicated login with `SELECT` grants only.
2. The role defaults to read-only transactions and the server verifies that state on startup.
3. Tables must exactly match `DB_ALLOWED_TABLES`; an empty allowlist is rejected.
4. Columns are loaded from database metadata and must match exactly.
5. Filter operators come from a small fixed set and values use prepared parameters.
6. Raw SQL, joins, expressions and multiple statements are not part of the API.
7. Result limits are enforced by the server, even when a client requests more.
8. PostgreSQL `statement_timeout` bounds query execution time.
9. Every call is audited with operation, outcome, duration and counts — never filter values or returned rows.
10. Internal database failures are not returned to the model.

MCP tool annotations also declare all tools read-only, non-destructive and closed-world. These annotations are hints to clients; database permissions remain the actual security boundary.

## Quick start with Docker

Requirements: Docker with Compose v2.

Build the PHP image and initialize PostgreSQL with fictitious demo data:

```bash
docker compose build
docker compose up -d db
```

Run an automated MCP session containing initialization, discovery and a filtered tool call:

```bash
docker compose run --rm --no-TTY server \
  < tests/fixtures/mcp-session.jsonl
```

The response stream contains only JSON-RPC messages. Audit events are written separately to the `audit-data` Docker volume.

Run the test suite:

```bash
docker compose --profile tools run --rm test
```

Stop the demo and remove its database and audit data:

```bash
docker compose down --volumes
```

## Connect an MCP client

Build the image first, then configure a STDIO-capable client. Replace `/absolute/path` with this repository's location:

```json
{
  "mcpServers": {
    "secure-demo-database": {
      "command": "docker",
      "args": [
        "compose",
        "-f",
        "/absolute/path/mcp-server-php/compose.yaml",
        "run",
        "--rm",
        "--no-TTY",
        "server"
      ]
    }
  }
}
```

The database container starts automatically through the Compose dependency. The MCP client communicates with the PHP process over stdin/stdout.

You can also inspect the server with the official MCP Inspector:

```bash
npx @modelcontextprotocol/inspector \
  docker compose run --rm --no-TTY server
```

## Run directly with PHP

Requirements:

- PHP 8.2 or newer;
- Composer;
- `pdo_pgsql` for PostgreSQL or `pdo_sqlite` for tests;
- a database identity with read-only privileges.

```bash
composer install
cp .env.example .env
set -a && . ./.env && set +a
php server.php
```

The server reads configuration from environment variables; it does not load `.env` files itself.

| Variable | Default | Description |
| --- | --- | --- |
| `DB_DSN` | local PostgreSQL DSN | PDO connection string. |
| `DB_USER` | `mcp_reader` | Dedicated database identity. |
| `DB_PASSWORD` | empty | Database password. |
| `DB_ALLOWED_TABLES` | demo tables | Comma-separated exact allowlist. |
| `DB_MAX_ROWS` | `100` | Hard result cap, from 1 to 1000. |
| `DB_QUERY_TIMEOUT_MS` | `3000` | Query timeout, from 100 to 60000 ms. |
| `DB_REQUIRE_READ_ONLY` | `true` | Refuse startup unless the connection reports read-only mode. |
| `AUDIT_LOG_PATH` | `var/audit.jsonl` | JSON Lines audit destination. |

Do not write debug output to stdout. STDIO reserves stdout exclusively for MCP messages; application diagnostics belong on stderr or in the audit log.

## Architecture

```text
MCP client
    |
    | JSON-RPC over stdio
    v
official MCP PHP SDK
    |
    +-- database://schema resource
    |
    +-- list_tables
    +-- describe_table
    +-- query_records
            |
            v
      DatabasePolicy
      exact identifiers
      prepared values
      forced row limit
            |
            v
      read-only PDO connection ----> JSONL audit log
```

The official SDK owns MCP lifecycle negotiation, input-schema validation, error framing, resources, structured tool output and transport behavior. This project owns the database policy and demo application.

## Tests and quality checks

```bash
composer test
composer analyse
composer check
```

The suite covers:

- exact table and column authorization;
- unsafe identifier rejection;
- filter operator validation and parameter binding;
- hard row limits and truncation reporting;
- startup refusal on a writable connection;
- redacted audit entries;
- a real MCP client/server exchange over STDIO, including resource discovery and a tool call.

GitHub Actions runs tests and PHPStan on PHP 8.2–8.5, plus a PostgreSQL end-to-end smoke test.

## Project status

The project is preparing for `v0.1.0`. The public API may change before that release. See [CHANGELOG.md](CHANGELOG.md), [SECURITY.md](SECURITY.md) and [CONTRIBUTING.md](CONTRIBUTING.md).

## License

MIT © Martin Šuránek. See [LICENSE](LICENSE).
