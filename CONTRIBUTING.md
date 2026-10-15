# Contributing

Requirements: PHP 8.2+, Composer and the `pdo_sqlite` extension.

```bash
composer install
composer check
```

Keep tool inputs structured and narrowly scoped. New database operations must preserve the exact table/column allowlist, parameterized values, bounded result sets, timeout configuration and audit trail. Do not write application logs to stdout because STDIO is reserved for MCP messages.
