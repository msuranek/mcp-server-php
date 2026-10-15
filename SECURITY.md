# Security policy

Please report vulnerabilities privately through GitHub Security Advisories rather than a public issue.

This project is a reference implementation, not a substitute for database authorization. Production deployments must use a dedicated database identity with `SELECT` access only to the intended objects. Keep `DB_REQUIRE_READ_ONLY=true`; the server refuses to start when PostgreSQL does not report a read-only transaction mode.

The server deliberately does not accept raw SQL. Reports about adding arbitrary SQL execution will be treated as feature proposals, not security fixes.
