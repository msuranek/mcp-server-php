#!/usr/bin/env php
<?php

declare(strict_types=1);

require __DIR__.'/vendor/autoload.php';

use Mcp\Server\Transport\StdioTransport;
use Msuranek\McpServerPhp\ApplicationFactory;
use Msuranek\McpServerPhp\Config\ServerConfig;

try {
    $server = ApplicationFactory::create(ServerConfig::fromEnvironment());
    exit($server->run(new StdioTransport()));
} catch (Throwable $exception) {
    fwrite(STDERR, '[secure-database-mcp] '.$exception->getMessage().PHP_EOL);
    exit(1);
}
