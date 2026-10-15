<?php

declare(strict_types=1);

namespace Msuranek\McpServerPhp\Database;

final class ReadOnlyDatabase
{
    private const OPERATORS = [
        'eq' => '=',
        'neq' => '<>',
        'lt' => '<',
        'lte' => '<=',
        'gt' => '>',
        'gte' => '>=',
        'like' => 'LIKE',
    ];

    public function __construct(
        private readonly \PDO $pdo,
        private readonly DatabasePolicy $policy,
        int $queryTimeoutMs = 3000,
        bool $requireReadOnly = true,
    ) {
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);
        $this->configureTimeout($queryTimeoutMs);

        if ($requireReadOnly && !$this->isReadOnly()) {
            throw new \RuntimeException('Database connection is not read-only. Refusing to start.');
        }
    }

    /** @return array{driver: string, read_only: bool, tables: list<array{name: string, columns: list<array{name: string, type: string, nullable: bool}>}>} */
    public function schema(): array
    {
        $tables = [];
        foreach ($this->policy->allowedTables() as $table) {
            $tables[] = ['name' => $table, 'columns' => $this->describeTable($table)];
        }

        return [
            'driver' => $this->driver(),
            'read_only' => $this->isReadOnly(),
            'tables' => $tables,
        ];
    }

    /** @return array{tables: list<string>} */
    public function listTables(): array
    {
        return ['tables' => $this->policy->allowedTables()];
    }

    /** @return list<array{name: string, type: string, nullable: bool}> */
    public function describeTable(string $table): array
    {
        $this->policy->assertTableAllowed($table);

        if ('pgsql' === $this->driver()) {
            $statement = $this->pdo->prepare(
                'SELECT column_name, data_type, is_nullable '
                .'FROM information_schema.columns '
                .'WHERE table_schema = current_schema() AND table_name = :table '
                .'ORDER BY ordinal_position',
            );
            $statement->execute(['table' => $table]);
            $rows = $statement->fetchAll();
            $columns = array_map(static fn (array $row): array => [
                'name' => (string) $row['column_name'],
                'type' => (string) $row['data_type'],
                'nullable' => 'YES' === $row['is_nullable'],
            ], $rows);
        } elseif ('sqlite' === $this->driver()) {
            $statement = $this->pdo->query('PRAGMA table_info('.$this->quoteIdentifier($table).')');
            if (false === $statement) {
                throw new \RuntimeException('Unable to inspect the database schema.');
            }
            $rows = $statement->fetchAll();
            $columns = array_map(static fn (array $row): array => [
                'name' => (string) $row['name'],
                'type' => strtolower((string) $row['type']),
                'nullable' => 0 === (int) $row['notnull'],
            ], $rows);
        } else {
            throw new \RuntimeException('Only PostgreSQL and SQLite are supported.');
        }

        if ($columns === []) {
            throw new \InvalidArgumentException("Allowed table '{$table}' does not exist.");
        }

        return array_values($columns);
    }

    /**
     * @param list<string> $columns
     * @param list<array{column?: mixed, operator?: mixed, value?: mixed}> $filters
     * @return array{table: string, columns: list<string>, rows: list<array<string, mixed>>, count: int, limit: int, truncated: bool}
     */
    public function query(
        string $table,
        array $columns = [],
        array $filters = [],
        int $limit = 50,
        ?string $orderBy = null,
        string $direction = 'asc',
    ): array {
        $this->policy->assertTableAllowed($table);
        $availableColumns = array_column($this->describeTable($table), 'name');
        $selectedColumns = $this->policy->columns($availableColumns, $columns);
        $limit = $this->policy->limit($limit);

        if (count($filters) > $this->policy->maxFilters) {
            throw new \InvalidArgumentException("At most {$this->policy->maxFilters} filters may be supplied.");
        }

        $available = array_fill_keys($availableColumns, true);
        $conditions = [];
        $parameters = [];
        foreach ($filters as $index => $filter) {
            $column = $filter['column'] ?? null;
            $operator = $filter['operator'] ?? 'eq';
            $value = $filter['value'] ?? null;
            if (!is_string($column) || !isset($available[$column])) {
                throw new \InvalidArgumentException("Filter {$index} references an unavailable column.");
            }
            if (!is_string($operator) || !isset(self::OPERATORS[$operator])) {
                throw new \InvalidArgumentException("Filter {$index} uses an unsupported operator.");
            }
            if (!is_scalar($value) && null !== $value) {
                throw new \InvalidArgumentException("Filter {$index} has an unsupported value.");
            }
            if ('like' === $operator && !is_string($value)) {
                throw new \InvalidArgumentException("Filter {$index} requires a string value for LIKE.");
            }

            $quotedColumn = $this->quoteIdentifier($column);
            if (null === $value && 'eq' === $operator) {
                $conditions[] = "{$quotedColumn} IS NULL";
                continue;
            }
            if (null === $value && 'neq' === $operator) {
                $conditions[] = "{$quotedColumn} IS NOT NULL";
                continue;
            }
            if (null === $value) {
                throw new \InvalidArgumentException("Filter {$index} cannot compare NULL with '{$operator}'.");
            }

            $parameter = ':filter_'.$index;
            $conditions[] = "{$quotedColumn} ".self::OPERATORS[$operator]." {$parameter}";
            $parameters[$parameter] = $value;
        }

        $orderSql = '';
        if (null !== $orderBy) {
            if (!isset($available[$orderBy])) {
                throw new \InvalidArgumentException("Order column '{$orderBy}' is not available.");
            }
            $direction = strtolower($direction);
            if (!in_array($direction, ['asc', 'desc'], true)) {
                throw new \InvalidArgumentException("Sort direction must be 'asc' or 'desc'.");
            }
            $orderSql = ' ORDER BY '.$this->quoteIdentifier($orderBy).' '.strtoupper($direction);
        }

        $sql = 'SELECT '.implode(', ', array_map($this->quoteIdentifier(...), $selectedColumns))
            .' FROM '.$this->quoteIdentifier($table)
            .($conditions === [] ? '' : ' WHERE '.implode(' AND ', $conditions))
            .$orderSql
            .' LIMIT :result_limit';

        $statement = $this->pdo->prepare($sql);
        foreach ($parameters as $name => $value) {
            $statement->bindValue($name, $value, match (true) {
                is_int($value) => \PDO::PARAM_INT,
                is_bool($value) => \PDO::PARAM_BOOL,
                default => \PDO::PARAM_STR,
            });
        }
        $statement->bindValue(':result_limit', $limit + 1, \PDO::PARAM_INT);
        $statement->execute();
        $rows = $statement->fetchAll();
        $truncated = count($rows) > $limit;
        if ($truncated) {
            array_pop($rows);
        }

        return [
            'table' => $table,
            'columns' => $selectedColumns,
            'rows' => array_values($rows),
            'count' => count($rows),
            'limit' => $limit,
            'truncated' => $truncated,
        ];
    }

    public function isReadOnly(): bool
    {
        return match ($this->driver()) {
            'pgsql' => 'on' === $this->scalarQuery('SHOW transaction_read_only'),
            'sqlite' => '1' === (string) $this->scalarQuery('PRAGMA query_only'),
            default => false,
        };
    }

    private function configureTimeout(int $milliseconds): void
    {
        match ($this->driver()) {
            'pgsql' => $this->pdo->exec('SET statement_timeout TO '.(int) $milliseconds),
            'sqlite' => $this->pdo->exec('PRAGMA busy_timeout = '.(int) $milliseconds),
            default => throw new \RuntimeException('Only PostgreSQL and SQLite are supported.'),
        };
    }

    private function driver(): string
    {
        return (string) $this->pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
    }

    private function quoteIdentifier(string $identifier): string
    {
        return '"'.str_replace('"', '""', $identifier).'"';
    }

    private function scalarQuery(string $sql): mixed
    {
        $statement = $this->pdo->query($sql);
        if (false === $statement) {
            throw new \RuntimeException('Unable to inspect the database connection.');
        }

        return $statement->fetchColumn();
    }
}
