<?php

declare(strict_types=1);

namespace stimmt\craft\Mcp\installer\clients;

use Override;

/**
 * MCP client configuration for Claude Code.
 *
 * Generates `.mcp.json` in the project root.
 */
final class ClaudeCode extends Base {
    public function getId(): string {
        return 'claude-code';
    }

    public function getName(): string {
        return 'Claude Code';
    }

    public function getDescription(): string {
        return '.mcp.json in project root';
    }

    public function getConfigPath(): string {
        return $this->configRoot . '/.mcp.json';
    }

    #[Override]
    protected function getDdevWrapperCommand(): string {
        return '${CLAUDE_PROJECT_DIR:-.}/' . self::WRAPPER_RELATIVE_PATH;
    }
}
