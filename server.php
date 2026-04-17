#!/usr/bin/env php
<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use Msuranek\McpServerPhp\McpServer;
use Msuranek\McpServerPhp\Tools\QueryDatabaseTool;

// --- Configure your data source here ---
$pdo = new PDO(
	dsn: 'mysql:host=127.0.0.1;dbname=' . ($_ENV['DB_NAME'] ?? 'mydb') . ';charset=utf8mb4',
	username: $_ENV['DB_USER'] ?? 'readonly_user',
	password: $_ENV['DB_PASS'] ?? '',
	options: [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
);

// Only expose these tables to Claude
$allowedTables = ['products', 'categories', 'orders'];

// --- Build and run server ---
$server = new McpServer('My App Database', '1.0.0');
$server->addTool(new QueryDatabaseTool($pdo, $allowedTables, maxRows: 100));
$server->run();
