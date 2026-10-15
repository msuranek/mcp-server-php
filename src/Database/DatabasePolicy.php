<?php

declare(strict_types=1);

namespace Msuranek\McpServerPhp\Database;

final readonly class DatabasePolicy
{
    /** @var array<string, true> */
    private array $allowedTableMap;

    /** @param list<string> $allowedTables */
    public function __construct(
        array $allowedTables,
        public int $maxRows = 100,
        public int $maxFilters = 20,
    ) {
        if ($allowedTables === []) {
            throw new \InvalidArgumentException('At least one table must be allowed.');
        }
        if ($this->maxRows < 1 || $this->maxRows > 1000) {
            throw new \InvalidArgumentException('The maximum row count must be between 1 and 1000.');
        }
        if ($this->maxFilters < 1 || $this->maxFilters > 100) {
            throw new \InvalidArgumentException('The maximum filter count must be between 1 and 100.');
        }

        $map = [];
        foreach ($allowedTables as $table) {
            if (!self::isIdentifier($table)) {
                throw new \InvalidArgumentException("Invalid allowed table name: {$table}");
            }
            $map[$table] = true;
        }
        $this->allowedTableMap = $map;
    }

    /** @return list<string> */
    public function allowedTables(): array
    {
        return array_keys($this->allowedTableMap);
    }

    public function assertTableAllowed(string $table): void
    {
        if (!isset($this->allowedTableMap[$table])) {
            throw new \InvalidArgumentException("Table '{$table}' is not allowed.");
        }
    }

    /**
     * @param list<string> $availableColumns
     * @param list<string> $requestedColumns
     * @return list<string>
     */
    public function columns(array $availableColumns, array $requestedColumns): array
    {
        if ($requestedColumns === []) {
            return $availableColumns;
        }
        if (count($requestedColumns) > 50) {
            throw new \InvalidArgumentException('At most 50 columns may be requested.');
        }

        $available = array_fill_keys($availableColumns, true);
        $normalized = [];
        foreach ($requestedColumns as $column) {
            if (!self::isIdentifier($column) || !isset($available[$column])) {
                throw new \InvalidArgumentException("Column '{$column}' is not available.");
            }
            $normalized[$column] = true;
        }

        return array_keys($normalized);
    }

    public function limit(int $requested): int
    {
        if ($requested < 1) {
            throw new \InvalidArgumentException('The row limit must be at least 1.');
        }

        return min($requested, $this->maxRows);
    }

    private static function isIdentifier(string $identifier): bool
    {
        return 1 === preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $identifier);
    }
}
