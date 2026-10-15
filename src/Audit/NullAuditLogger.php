<?php

declare(strict_types=1);

namespace Msuranek\McpServerPhp\Audit;

final class NullAuditLogger implements AuditLogger
{
    public function record(string $operation, string $status, float $durationMs, array $context = []): void
    {
    }
}
