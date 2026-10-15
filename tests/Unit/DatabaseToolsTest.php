<?php

declare(strict_types=1);

namespace Msuranek\McpServerPhp\Tests\Unit;

use Mcp\Exception\ToolCallException;
use Msuranek\McpServerPhp\Tests\Support\DatabaseFixture;
use Msuranek\McpServerPhp\Tests\Support\InMemoryAuditLogger;
use Msuranek\McpServerPhp\Tools\DatabaseTools;
use PHPUnit\Framework\TestCase;

final class DatabaseToolsTest extends TestCase
{
    public function testItAuditsSuccessfulCallsWithoutFilterValues(): void
    {
        $audit = new InMemoryAuditLogger();
        $tools = new DatabaseTools(DatabaseFixture::create(), $audit);

        $tools->queryRecords('customers', filters: [[
            'column' => 'region',
            'operator' => 'eq',
            'value' => 'EU',
        ]]);

        self::assertSame('success', $audit->entries[0]['status']);
        self::assertSame(1, $audit->entries[0]['context']['filter_count']);
        self::assertStringNotContainsString('EU', json_encode($audit->entries, \JSON_THROW_ON_ERROR));
    }

    public function testItReturnsSafeToolErrorsAndAuditsRejections(): void
    {
        $audit = new InMemoryAuditLogger();
        $tools = new DatabaseTools(DatabaseFixture::create(), $audit);

        try {
            $tools->describeTable('secrets');
            self::fail('Expected a rejected tool call.');
        } catch (ToolCallException $exception) {
            self::assertSame("Table 'secrets' is not allowed.", $exception->getMessage());
        }

        self::assertSame('rejected', $audit->entries[0]['status']);
        self::assertArrayNotHasKey('message', $audit->entries[0]['context']);
    }
}
