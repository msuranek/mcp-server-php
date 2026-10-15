<?php

declare(strict_types=1);

namespace Msuranek\McpServerPhp\Tools;

use Mcp\Exception\ToolCallException;
use Msuranek\McpServerPhp\Audit\AuditLogger;
use Msuranek\McpServerPhp\Database\ReadOnlyDatabase;

final readonly class DatabaseTools
{
    public function __construct(
        private ReadOnlyDatabase $database,
        private AuditLogger $audit,
    ) {
    }

    /** @return array{tables: list<string>} */
    public function listTables(): array
    {
        return $this->execute('list_tables', [], fn (): array => $this->database->listTables());
    }

    /** @return array{table: string, columns: list<array{name: string, type: string, nullable: bool}>} */
    public function describeTable(string $table): array
    {
        return $this->execute('describe_table', ['table' => $table], fn (): array => [
            'table' => $table,
            'columns' => $this->database->describeTable($table),
        ]);
    }

    /**
     * @param list<string> $columns
     * @param list<array{column?: mixed, operator?: mixed, value?: mixed}> $filters
     * @return array{table: string, columns: list<string>, rows: list<array<string, mixed>>, count: int, limit: int, truncated: bool}
     */
    public function queryRecords(
        string $table,
        array $columns = [],
        array $filters = [],
        int $limit = 50,
        ?string $orderBy = null,
        string $direction = 'asc',
    ): array {
        return $this->execute('query_records', [
            'table' => $table,
            'column_count' => count($columns),
            'filter_count' => count($filters),
            'requested_limit' => $limit,
        ], fn (): array => $this->database->query($table, $columns, $filters, $limit, $orderBy, $direction));
    }

    /**
     * @template T
     * @param array<string, bool|float|int|string|null> $context
     * @param callable(): T $callback
     * @return T
     */
    private function execute(string $operation, array $context, callable $callback): mixed
    {
        $startedAt = hrtime(true);
        try {
            $result = $callback();
            $this->audit->record($operation, 'success', $this->elapsedMs($startedAt), $context + [
                'result_count' => isset($result['count']) && is_int($result['count']) ? $result['count'] : null,
            ]);

            return $result;
        } catch (\InvalidArgumentException $exception) {
            $this->audit->record($operation, 'rejected', $this->elapsedMs($startedAt), $context + [
                'error_type' => $exception::class,
            ]);
            throw new ToolCallException($exception->getMessage(), previous: $exception);
        } catch (\Throwable $exception) {
            $this->audit->record($operation, 'error', $this->elapsedMs($startedAt), $context + [
                'error_type' => $exception::class,
            ]);
            throw new ToolCallException('The database operation failed safely. Check the server audit log.', previous: $exception);
        }
    }

    private function elapsedMs(int $startedAt): float
    {
        return (hrtime(true) - $startedAt) / 1_000_000;
    }
}
