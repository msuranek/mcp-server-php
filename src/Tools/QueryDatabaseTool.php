<?php

declare(strict_types=1);

namespace Msuranek\McpServerPhp\Tools;

/**
 * Example tool: executes read-only SQL queries against a PDO database.
 *
 * Security: only SELECT statements are allowed; a dedicated read-only DB user
 * is strongly recommended.
 */
class QueryDatabaseTool implements ToolInterface
{
	/** Tables that Claude is allowed to query. Empty array = allow all (not recommended). */
	private array $allowedTables;


	public function __construct(
		private readonly \PDO $pdo,
		array $allowedTables = [],
		private readonly int $maxRows = 100,
	) {
		$this->allowedTables = $allowedTables;
	}


	public function getName(): string
	{
		return 'query_database';
	}


	public function getDescription(): string
	{
		return 'Executes a read-only SQL SELECT query and returns the results as JSON. '
			. 'Only SELECT statements are permitted.';
	}


	/** @return array<mixed> */
	public function getInputSchema(): array
	{
		return [
			'type' => 'object',
			'properties' => [
				'sql' => [
					'type' => 'string',
					'description' => 'A SQL SELECT statement to execute',
				],
				'limit' => [
					'type' => 'integer',
					'description' => 'Maximum number of rows to return (default: 50, max: ' . $this->maxRows . ')',
					'default' => 50,
				],
			],
			'required' => ['sql'],
		];
	}


	/** @param array<mixed> $input */
	public function execute(array $input): mixed
	{
		$sql = trim((string) ($input['sql'] ?? ''));
		$limit = min((int) ($input['limit'] ?? 50), $this->maxRows);

		$this->validateSql($sql);

		// Append LIMIT if not already present
		if (!preg_match('/\bLIMIT\b/i', $sql)) {
			$sql .= ' LIMIT ' . $limit;
		}

		$stmt = $this->pdo->query($sql);
		$rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

		return [
			'rows' => $rows,
			'count' => count($rows),
		];
	}


	private function validateSql(string $sql): void
	{
		if (!preg_match('/^\s*SELECT\b/i', $sql)) {
			throw new \InvalidArgumentException('Only SELECT statements are allowed.');
		}

		if (preg_match('/\b(INSERT|UPDATE|DELETE|DROP|TRUNCATE|ALTER|CREATE|EXEC|EXECUTE)\b/i', $sql)) {
			throw new \InvalidArgumentException('Forbidden keyword detected in SQL.');
		}

		if ($this->allowedTables !== []) {
			foreach ($this->allowedTables as $table) {
				if (stripos($sql, $table) !== false) {
					return;
				}
			}
			throw new \InvalidArgumentException(
				'Query references tables not in the allowlist: ' . implode(', ', $this->allowedTables),
			);
		}
	}
}
