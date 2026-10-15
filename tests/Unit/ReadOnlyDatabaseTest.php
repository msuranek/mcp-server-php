<?php

declare(strict_types=1);

namespace Msuranek\McpServerPhp\Tests\Unit;

use Msuranek\McpServerPhp\Database\DatabasePolicy;
use Msuranek\McpServerPhp\Database\ReadOnlyDatabase;
use Msuranek\McpServerPhp\Tests\Support\DatabaseFixture;
use PHPUnit\Framework\TestCase;

final class ReadOnlyDatabaseTest extends TestCase
{
    public function testItReturnsOnlyAllowedColumnsAndRows(): void
    {
        $database = DatabaseFixture::create();

        $result = $database->query(
            table: 'customers',
            columns: ['company_name'],
            filters: [['column' => 'region', 'operator' => 'eq', 'value' => 'EU']],
            limit: 10,
            orderBy: 'company_name',
        );

        self::assertSame(['company_name'], $result['columns']);
        self::assertSame([
            ['company_name' => 'Northwind Labs'],
            ['company_name' => 'Summit Works'],
        ], $result['rows']);
        self::assertSame(2, $result['limit']);
        self::assertFalse($result['truncated']);
    }

    public function testItSignalsTruncatedResults(): void
    {
        $database = DatabaseFixture::create();

        $result = $database->query('customers', limit: 1, orderBy: 'id');

        self::assertSame(1, $result['count']);
        self::assertTrue($result['truncated']);
    }

    public function testItRejectsAnUnknownColumn(): void
    {
        $database = DatabaseFixture::create();

        $this->expectExceptionMessage("Column 'password' is not available.");
        $database->query('customers', columns: ['password']);
    }

    public function testItRejectsAnUnsupportedOperator(): void
    {
        $database = DatabaseFixture::create();

        $this->expectExceptionMessage('uses an unsupported operator');
        $database->query('customers', filters: [[
            'column' => 'id',
            'operator' => 'or 1=1',
            'value' => 1,
        ]]);
    }

    public function testFilterValuesCannotChangeTheQueryStructure(): void
    {
        $database = DatabaseFixture::create();

        $result = $database->query('customers', filters: [[
            'column' => 'region',
            'operator' => 'eq',
            'value' => "EU' OR 1=1 --",
        ]]);

        self::assertSame([], $result['rows']);
        self::assertSame(['tables' => ['customers']], $database->listTables());
    }

    public function testItRefusesAWritableConnectionWhenRequired(): void
    {
        $pdo = new \PDO('sqlite::memory:');
        $pdo->exec('CREATE TABLE customers (id INTEGER PRIMARY KEY)');

        $this->expectExceptionMessage('Database connection is not read-only');
        new ReadOnlyDatabase($pdo, new DatabasePolicy(['customers']), requireReadOnly: true);
    }
}
