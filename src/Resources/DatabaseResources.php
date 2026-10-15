<?php

declare(strict_types=1);

namespace Msuranek\McpServerPhp\Resources;

use Msuranek\McpServerPhp\Database\ReadOnlyDatabase;

final readonly class DatabaseResources
{
    public function __construct(private ReadOnlyDatabase $database)
    {
    }

    /** @return array{driver: string, read_only: bool, tables: list<array{name: string, columns: list<array{name: string, type: string, nullable: bool}>}>} */
    public function schema(): array
    {
        return $this->database->schema();
    }
}
