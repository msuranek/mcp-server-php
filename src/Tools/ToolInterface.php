<?php

declare(strict_types=1);

namespace Msuranek\McpServerPhp\Tools;

interface ToolInterface
{
	public function getName(): string;

	public function getDescription(): string;

	/** @return array<mixed> JSON Schema object */
	public function getInputSchema(): array;

	/** @param array<mixed> $input */
	public function execute(array $input): mixed;
}
