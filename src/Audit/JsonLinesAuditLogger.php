<?php

declare(strict_types=1);

namespace Msuranek\McpServerPhp\Audit;

final readonly class JsonLinesAuditLogger implements AuditLogger
{
    public function __construct(private string $path)
    {
        $directory = dirname($this->path);
        if (!is_dir($directory) && !mkdir($directory, 0770, true) && !is_dir($directory)) {
            throw new \RuntimeException("Cannot create audit directory: {$directory}");
        }
    }

    public function record(string $operation, string $status, float $durationMs, array $context = []): void
    {
        $entry = [
            'timestamp' => gmdate('c'),
            'operation' => $operation,
            'status' => $status,
            'duration_ms' => round($durationMs, 2),
            'context' => $context,
        ];
        $json = json_encode($entry, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES)."\n";
        $handle = fopen($this->path, 'ab');
        if (false === $handle) {
            throw new \RuntimeException('Cannot open the audit log.');
        }

        try {
            if (!flock($handle, \LOCK_EX)) {
                throw new \RuntimeException('Cannot lock the audit log.');
            }
            if (false === fwrite($handle, $json)) {
                throw new \RuntimeException('Cannot write the audit log.');
            }
            fflush($handle);
            flock($handle, \LOCK_UN);
        } finally {
            fclose($handle);
        }
    }
}
