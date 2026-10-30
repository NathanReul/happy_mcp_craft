<?php

declare(strict_types=1);

namespace stimmt\craft\Mcp\installer\clients;

use Override;

/**
 * MCP client configuration for Google Antigravity (AGY).
 *
 * Generates a workspace plugin in `.agent/plugins/craft-cms/` containing
 * `plugin.json` and `mcp_config.json`.
 */
final class Antigravity extends Base {
    public const string PLUGIN_DIR = '.agent/plugins/craft-cms';

    public const string PLUGIN_NAME = 'craft-cms';

    public function getId(): string {
        return 'antigravity';
    }

    public function getName(): string {
        return 'Antigravity';
    }

    public function getDescription(): string {
        return self::PLUGIN_DIR . '/mcp_config.json';
    }

    public function getConfigPath(): string {
        return $this->configRoot . '/' . self::PLUGIN_DIR . '/mcp_config.json';
    }

    public function getPluginManifestPath(): string {
        return $this->configRoot . '/' . self::PLUGIN_DIR . '/plugin.json';
    }

    /**
     * Manifest content required by Antigravity to discover the workspace plugin.
     *
     * @return array{name: string, displayName: string, description: string}
     */
    public function getPluginManifest(): array {
        return [
            'name' => self::PLUGIN_NAME,
            'displayName' => 'Craft CMS MCP',
            'description' => 'Craft CMS Model Context Protocol (MCP) server',
        ];
    }

    #[Override]
    protected function getDdevWrapperCommand(): string {
        return './' . self::WRAPPER_RELATIVE_PATH;
    }
}
