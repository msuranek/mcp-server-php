<?php

declare(strict_types=1);

namespace Msuranek\McpServerPhp\Tests\Unit;

use Msuranek\McpServerPhp\Database\DatabasePolicy;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DatabasePolicyTest extends TestCase
{
    public function testItRequiresAnExplicitAllowlist(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new DatabasePolicy([]);
    }

    public function testItRejectsTablesOutsideTheAllowlist(): void
    {
        $policy = new DatabasePolicy(['customers']);

        $this->expectExceptionMessage("Table 'secrets' is not allowed.");
        $policy->assertTableAllowed('secrets');
    }

    public function testItClampsTheRequestedLimit(): void
    {
        $policy = new DatabasePolicy(['customers'], 25);

        self::assertSame(25, $policy->limit(1000));
    }

    #[DataProvider('invalidIdentifierProvider')]
    public function testItRejectsUnsafeConfiguredIdentifiers(string $identifier): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new DatabasePolicy([$identifier]);
    }

    /** @return iterable<string, array{string}> */
    public static function invalidIdentifierProvider(): iterable
    {
        yield 'qualified name' => ['public.customers'];
        yield 'statement terminator' => ['customers; DROP TABLE users'];
        yield 'quoted identifier' => ['"customers"'];
        yield 'whitespace' => ['customer data'];
    }
}
