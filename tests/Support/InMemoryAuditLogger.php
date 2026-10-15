<?php

declare(strict_types=1);

namespace Msuranek\McpServerPhp\Tests\Support;

use Msuranek\McpServerPhp\Audit\AuditLogger;

final class InMemoryAuditLogger implements AuditLogger
{
    /** @var list<array{operation: string, status: string, duration_ms: float, context: array<string, bool|float|int|string|null>}> */
    public array $entries = [];

    public function record(string $operation, string $status, float $durationMs, array $context = []): void
    {
        $this->entries[] = [
            'operation' => $operation,
            'status' => $status,
            'duration_ms' => $durationMs,
            'context' => $context,
        ];
    }
}
