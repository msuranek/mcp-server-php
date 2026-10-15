<?php

declare(strict_types=1);

namespace Msuranek\McpServerPhp;

use Mcp\Schema\ToolAnnotations;
use Mcp\Server;
use Msuranek\McpServerPhp\Audit\AuditLogger;
use Msuranek\McpServerPhp\Audit\JsonLinesAuditLogger;
use Msuranek\McpServerPhp\Config\ServerConfig;
use Msuranek\McpServerPhp\Database\DatabasePolicy;
use Msuranek\McpServerPhp\Database\ReadOnlyDatabase;
use Msuranek\McpServerPhp\Resources\DatabaseResources;
use Msuranek\McpServerPhp\Tools\DatabaseTools;

final class ApplicationFactory
{
    public static function create(ServerConfig $config, ?AuditLogger $audit = null): Server
    {
        $pdo = new \PDO($config->dsn, $config->username, $config->password, [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            \PDO::ATTR_EMULATE_PREPARES => false,
        ]);

        $database = new ReadOnlyDatabase(
            $pdo,
            new DatabasePolicy($config->allowedTables, $config->maxRows),
            $config->queryTimeoutMs,
            $config->requireReadOnly,
        );
        $tools = new DatabaseTools($database, $audit ?? new JsonLinesAuditLogger($config->auditLogPath));
        $resources = new DatabaseResources($database);
        $readOnly = new ToolAnnotations(
            readOnlyHint: true,
            destructiveHint: false,
            idempotentHint: true,
            openWorldHint: false,
        );

        return Server::builder()
            ->setServerInfo(
                'secure-database-mcp',
                '0.1.0',
                'A constrained read-only database MCP server for PHP.',
                websiteUrl: 'https://github.com/msuranek/mcp-server-php',
                title: 'Secure Database MCP',
            )
            ->setInstructions(
                'Use database://schema before querying. Only explicitly allowed tables and structured filters are available; raw SQL is intentionally not accepted.',
            )
            ->addTool(
                [$tools, 'listTables'],
                name: 'list_tables',
                title: 'List allowed database tables',
                description: 'Lists only the database tables explicitly exposed by the server allowlist.',
                annotations: $readOnly,
                inputSchema: self::objectSchema([]),
                outputSchema: [
                    'type' => 'object',
                    'properties' => [
                        'tables' => ['type' => 'array', 'items' => ['type' => 'string']],
                    ],
                    'required' => ['tables'],
                    'additionalProperties' => false,
                ],
            )
            ->addTool(
                [$tools, 'describeTable'],
                name: 'describe_table',
                title: 'Describe an allowed table',
                description: 'Returns column names, database types and nullability for one allowed table.',
                annotations: $readOnly,
                inputSchema: self::objectSchema([
                    'table' => ['type' => 'string', 'description' => 'An exact table name returned by list_tables.'],
                ], ['table']),
                outputSchema: [
                    'type' => 'object',
                    'properties' => [
                        'table' => ['type' => 'string'],
                        'columns' => [
                            'type' => 'array',
                            'items' => [
                                'type' => 'object',
                                'properties' => [
                                    'name' => ['type' => 'string'],
                                    'type' => ['type' => 'string'],
                                    'nullable' => ['type' => 'boolean'],
                                ],
                                'required' => ['name', 'type', 'nullable'],
                                'additionalProperties' => false,
                            ],
                        ],
                    ],
                    'required' => ['table', 'columns'],
                    'additionalProperties' => false,
                ],
            )
            ->addTool(
                [$tools, 'queryRecords'],
                name: 'query_records',
                title: 'Query allowed records safely',
                description: 'Runs a server-built SELECT query. Raw SQL, joins, expressions and multiple statements are not accepted.',
                annotations: $readOnly,
                inputSchema: self::queryInputSchema(),
                outputSchema: self::queryOutputSchema(),
            )
            ->addResource(
                [$resources, 'schema'],
                uri: 'database://schema',
                name: 'database_schema',
                title: 'Allowed database schema',
                description: 'Machine-readable schema for tables exposed by this server.',
                mimeType: 'application/json',
            )
            ->build();
    }

    /**
     * @param array<string, array<string, mixed>> $properties
     * @param list<string> $required
     * @return array<string, mixed>
     */
    private static function objectSchema(array $properties, array $required = []): array
    {
        return [
            'type' => 'object',
            'properties' => $properties,
            'required' => $required,
            'additionalProperties' => false,
        ];
    }

    /** @return array<string, mixed> */
    private static function queryInputSchema(): array
    {
        return self::objectSchema([
            'table' => ['type' => 'string', 'description' => 'An exact table name returned by list_tables.'],
            'columns' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
                'maxItems' => 50,
                'description' => 'Columns to return. An empty list selects all columns.',
            ],
            'filters' => [
                'type' => 'array',
                'maxItems' => 20,
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'column' => ['type' => 'string'],
                        'operator' => ['type' => 'string', 'enum' => ['eq', 'neq', 'lt', 'lte', 'gt', 'gte', 'like']],
                        'value' => ['type' => ['string', 'number', 'integer', 'boolean', 'null']],
                    ],
                    'required' => ['column', 'value'],
                    'additionalProperties' => false,
                ],
            ],
            'limit' => ['type' => 'integer', 'minimum' => 1, 'default' => 50],
            'orderBy' => ['type' => ['string', 'null']],
            'direction' => ['type' => 'string', 'enum' => ['asc', 'desc'], 'default' => 'asc'],
        ], ['table']);
    }

    /** @return array<string, mixed> */
    private static function queryOutputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'table' => ['type' => 'string'],
                'columns' => ['type' => 'array', 'items' => ['type' => 'string']],
                'rows' => ['type' => 'array', 'items' => ['type' => 'object']],
                'count' => ['type' => 'integer'],
                'limit' => ['type' => 'integer'],
                'truncated' => ['type' => 'boolean'],
            ],
            'required' => ['table', 'columns', 'rows', 'count', 'limit', 'truncated'],
            'additionalProperties' => false,
        ];
    }
}
