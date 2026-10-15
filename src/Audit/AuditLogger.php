<?php

declare(strict_types=1);

namespace Msuranek\McpServerPhp\Audit;

interface AuditLogger
{
    /** @param array<string, bool|float|int|string|null> $context */
    public function record(string $operation, string $status, float $durationMs, array $context = []): void;
}
