<?php

declare(strict_types=1);

namespace Msuranek\McpServerPhp;

use Msuranek\McpServerPhp\Tools\ToolInterface;

/**
 * MCP Server skeleton for PHP.
 * Communicates over stdio using JSON-RPC 2.0 as per MCP specification.
 *
 * Usage:
 *   $server = new McpServer('My Database Server', '1.0.0');
 *   $server->addTool(new QueryDatabaseTool($pdo));
 *   $server->run();
 */
class McpServer
{
	/** @var ToolInterface[] */
	private array $tools = [];


	public function __construct(
		private readonly string $serverName,
		private readonly string $serverVersion = '1.0.0',
	) {
	}


	public function addTool(ToolInterface $tool): self
	{
		$this->tools[$tool->getName()] = $tool;
		return $this;
	}


	public function run(): void
	{
		while ($line = fgets(STDIN)) {
			$line = trim($line);
			if ($line === '') {
				continue;
			}

			$request = json_decode($line, true);
			if ($request === null) {
				$this->writeError(null, -32700, 'Parse error');
				continue;
			}

			$response = $this->dispatch($request);
			$this->write($response);
		}
	}


	/** @param array<mixed> $request */
	private function dispatch(array $request): mixed
	{
		$id = $request['id'] ?? null;
		$method = $request['method'] ?? '';

		return match ($method) {
			'initialize'  => $this->handleInitialize($id),
			'tools/list'  => $this->handleToolsList($id),
			'tools/call'  => $this->handleToolCall($id, $request['params'] ?? []),
			default       => $this->makeError($id, -32601, 'Method not found: ' . $method),
		};
	}


	private function handleInitialize(mixed $id): mixed
	{
		return [
			'jsonrpc' => '2.0',
			'id' => $id,
			'result' => [
				'protocolVersion' => '2024-11-05',
				'capabilities' => ['tools' => []],
				'serverInfo' => [
					'name' => $this->serverName,
					'version' => $this->serverVersion,
				],
			],
		];
	}


	private function handleToolsList(mixed $id): mixed
	{
		$toolDefinitions = array_map(
			fn(ToolInterface $tool) => [
				'name' => $tool->getName(),
				'description' => $tool->getDescription(),
				'inputSchema' => $tool->getInputSchema(),
			],
			$this->tools,
		);

		return [
			'jsonrpc' => '2.0',
			'id' => $id,
			'result' => ['tools' => array_values($toolDefinitions)],
		];
	}


	/** @param array<mixed> $params */
	private function handleToolCall(mixed $id, array $params): mixed
	{
		$toolName = $params['name'] ?? '';
		$input = $params['arguments'] ?? [];

		if (!isset($this->tools[$toolName])) {
			return $this->makeError($id, -32602, 'Unknown tool: ' . $toolName);
		}

		try {
			$result = $this->tools[$toolName]->execute($input);
			return [
				'jsonrpc' => '2.0',
				'id' => $id,
				'result' => [
					'content' => [
						['type' => 'text', 'text' => is_string($result) ? $result : json_encode($result)],
					],
				],
			];
		} catch (\Throwable $e) {
			return [
				'jsonrpc' => '2.0',
				'id' => $id,
				'result' => [
					'content' => [['type' => 'text', 'text' => 'Error: ' . $e->getMessage()]],
					'isError' => true,
				],
			];
		}
	}


	private function makeError(mixed $id, int $code, string $message): mixed
	{
		return [
			'jsonrpc' => '2.0',
			'id' => $id,
			'error' => ['code' => $code, 'message' => $message],
		];
	}


	private function writeError(mixed $id, int $code, string $message): void
	{
		$this->write($this->makeError($id, $code, $message));
	}


	private function write(mixed $data): void
	{
		echo json_encode($data) . "\n";
		if (ob_get_level() > 0) {
			ob_flush();
		}
		flush();
	}
}
