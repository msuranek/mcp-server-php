<?php

declare(strict_types=1);

namespace Msuranek\McpServerPhp\Tests\Integration;

use Mcp\Client;
use Mcp\Client\Transport\StdioTransport;
use Mcp\Schema\Content\TextResourceContents;
use PHPUnit\Framework\TestCase;

final class McpServerTest extends TestCase
{
    private string $databasePath;
    private string $auditPath;

    protected function setUp(): void
    {
        $databasePath = tempnam(sys_get_temp_dir(), 'mcp-db-');
        $auditPath = tempnam(sys_get_temp_dir(), 'mcp-audit-');
        if (false === $databasePath || false === $auditPath) {
            self::fail('Unable to create integration test files.');
        }
        $this->databasePath = $databasePath;
        $this->auditPath = $auditPath;

        $pdo = new \PDO('sqlite:'.$this->databasePath);
        $pdo->exec('CREATE TABLE reports (id INTEGER PRIMARY KEY, label TEXT NOT NULL, score INTEGER NOT NULL)');
        $pdo->exec("INSERT INTO reports (label, score) VALUES ('alpha', 90), ('beta', 72)");
    }

    protected function tearDown(): void
    {
        @unlink($this->databasePath);
        @unlink($this->auditPath);
    }

    public function testClientCanDiscoverAndCallTheServerOverStdio(): void
    {
        $client = Client::builder()
            ->setClientInfo('integration-test', '1.0.0')
            ->build();
        $transport = new StdioTransport(
            command: \PHP_BINARY,
            args: [dirname(__DIR__, 2).'/server.php'],
            cwd: dirname(__DIR__, 2),
            env: [
                'DB_DSN' => 'sqlite:'.$this->databasePath,
                'DB_USER' => 'unused',
                'DB_PASSWORD' => 'unused',
                'DB_ALLOWED_TABLES' => 'reports',
                'DB_MAX_ROWS' => '10',
                'DB_QUERY_TIMEOUT_MS' => '1000',
                'DB_REQUIRE_READ_ONLY' => 'false',
                'AUDIT_LOG_PATH' => $this->auditPath,
            ],
        );

        try {
            $client->connect($transport);

            self::assertSame(
                ['list_tables', 'describe_table', 'query_records'],
                array_values(array_map(static fn ($tool): string => $tool->name, $client->listTools()->tools)),
            );

            $result = $client->callTool('query_records', [
                'table' => 'reports',
                'columns' => ['label', 'score'],
                'filters' => [['column' => 'score', 'operator' => 'gte', 'value' => 80]],
                'orderBy' => 'score',
                'direction' => 'desc',
            ]);
            self::assertFalse($result->isError);
            self::assertSame('alpha', $result->structuredContent['rows'][0]['label']);

            $resources = $client->listResources()->resources;
            self::assertSame('database://schema', $resources[0]->uri);
            $resource = $client->readResource('database://schema');
            self::assertInstanceOf(TextResourceContents::class, $resource->contents[0]);
            self::assertStringContainsString('reports', (string) $resource->contents[0]->text);
        } finally {
            $client->disconnect();
        }

        $audit = file_get_contents($this->auditPath);
        self::assertIsString($audit);
        self::assertStringContainsString('query_records', $audit);
        self::assertStringNotContainsString('alpha', $audit);
    }
}
