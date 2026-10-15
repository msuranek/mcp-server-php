<?php

declare(strict_types=1);

namespace Msuranek\McpServerPhp\Config;

final readonly class ServerConfig
{
    /** @param list<string> $allowedTables */
    public function __construct(
        public string $dsn,
        public string $username,
        public string $password,
        public array $allowedTables,
        public int $maxRows,
        public int $queryTimeoutMs,
        public bool $requireReadOnly,
        public string $auditLogPath,
    ) {
        if ($this->allowedTables === []) {
            throw new \InvalidArgumentException('DB_ALLOWED_TABLES must contain at least one table.');
        }
        if ($this->maxRows < 1 || $this->maxRows > 1000) {
            throw new \InvalidArgumentException('DB_MAX_ROWS must be between 1 and 1000.');
        }
        if ($this->queryTimeoutMs < 100 || $this->queryTimeoutMs > 60_000) {
            throw new \InvalidArgumentException('DB_QUERY_TIMEOUT_MS must be between 100 and 60000.');
        }
        foreach ($this->allowedTables as $table) {
            if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $table)) {
                throw new \InvalidArgumentException("Invalid table name in DB_ALLOWED_TABLES: {$table}");
            }
        }
    }

    public static function fromEnvironment(): self
    {
        $allowedTables = array_values(array_filter(array_map(
            static fn (string $table): string => trim($table),
            explode(',', self::env('DB_ALLOWED_TABLES', 'customers,orders,order_items')),
        )));

        return new self(
            dsn: self::env('DB_DSN', 'pgsql:host=127.0.0.1;port=5432;dbname=mcp_demo'),
            username: self::env('DB_USER', 'mcp_reader'),
            password: self::env('DB_PASSWORD', ''),
            allowedTables: $allowedTables,
            maxRows: self::envInt('DB_MAX_ROWS', 100),
            queryTimeoutMs: self::envInt('DB_QUERY_TIMEOUT_MS', 3000),
            requireReadOnly: self::envBool('DB_REQUIRE_READ_ONLY', true),
            auditLogPath: self::env('AUDIT_LOG_PATH', dirname(__DIR__, 2).'/var/audit.jsonl'),
        );
    }

    private static function env(string $name, string $default): string
    {
        $value = getenv($name);

        return false === $value || '' === $value ? $default : $value;
    }

    private static function envInt(string $name, int $default): int
    {
        $value = getenv($name);
        if (false === $value || '' === $value) {
            return $default;
        }
        if (!preg_match('/^\d+$/', $value)) {
            throw new \InvalidArgumentException("{$name} must be an integer.");
        }

        return (int) $value;
    }

    private static function envBool(string $name, bool $default): bool
    {
        $value = getenv($name);
        if (false === $value || '' === $value) {
            return $default;
        }

        return match (strtolower($value)) {
            '1', 'true', 'yes', 'on' => true,
            '0', 'false', 'no', 'off' => false,
            default => throw new \InvalidArgumentException("{$name} must be a boolean."),
        };
    }
}
