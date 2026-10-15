<?php

declare(strict_types=1);

namespace Msuranek\McpServerPhp\Tests\Support;

use Msuranek\McpServerPhp\Database\DatabasePolicy;
use Msuranek\McpServerPhp\Database\ReadOnlyDatabase;

final class DatabaseFixture
{
    public static function create(bool $readOnly = true): ReadOnlyDatabase
    {
        $pdo = new \PDO('sqlite::memory:');
        $pdo->exec('CREATE TABLE customers (id INTEGER PRIMARY KEY, company_name TEXT NOT NULL, region TEXT NOT NULL)');
        $pdo->exec("INSERT INTO customers (company_name, region) VALUES ('Northwind Labs', 'EU'), ('Blue Oak Studio', 'NA'), ('Summit Works', 'EU')");
        if ($readOnly) {
            $pdo->exec('PRAGMA query_only = ON');
        }

        return new ReadOnlyDatabase($pdo, new DatabasePolicy(['customers'], 2), 1000, $readOnly);
    }
}
