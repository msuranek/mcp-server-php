# mcp-server-php

PHP skeleton for building [Model Context Protocol (MCP)](https://modelcontextprotocol.io) servers. Connect Claude to your database, internal API, or any data source in minutes.

## What is MCP?

MCP is an open standard by Anthropic that lets Claude (and other AI models) interact with external tools and data sources in a standardized way. An MCP server exposes **tools** that Claude can call — like querying a database, reading files, or calling an API.

## Features

- Clean `ToolInterface` for registering any number of tools
- Built-in `QueryDatabaseTool` with SQL injection protection and table allowlist
- Communicates over `stdio` (compatible with Claude Desktop, Claude Code)
- Zero dependencies beyond PHP 8.1

## Quick Start

```bash
composer install
cp .env.example .env  # fill in your DB credentials
php server.php
```

### Register in Claude Desktop (`claude_desktop_config.json`)

```json
{
  "mcpServers": {
    "my-app-db": {
      "command": "php",
      "args": ["/absolute/path/to/server.php"]
    }
  }
}
```

Restart Claude Desktop — you'll see the `query_database` tool available.

## Adding Custom Tools

Implement `ToolInterface`:

```php
class GetUserTool implements ToolInterface
{
    public function getName(): string { return 'get_user'; }

    public function getDescription(): string
    {
        return 'Returns user data by ID';
    }

    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => ['id' => ['type' => 'integer']],
            'required' => ['id'],
        ];
    }

    public function execute(array $input): mixed
    {
        // your logic here
        return ['id' => $input['id'], 'name' => 'Jan Novák'];
    }
}
```

Then register it:

```php
$server->addTool(new GetUserTool());
```

## Security

- `QueryDatabaseTool` only permits `SELECT` statements
- Configure `$allowedTables` to restrict which tables Claude can access
- Use a **dedicated read-only database user** — never connect as root
- Validate and sanitize all tool inputs before use

## License

MIT — [Martin Šuránek](https://github.com/msuranek)
