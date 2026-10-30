<?php

declare(strict_types=1);

use stimmt\craft\Mcp\installer\clients\Antigravity;
use stimmt\craft\Mcp\installer\clients\Base;
use stimmt\craft\Mcp\installer\clients\ClaudeCode;
use stimmt\craft\Mcp\installer\clients\Cursor;
use stimmt\craft\Mcp\installer\ConfigWriter;
use stimmt\craft\Mcp\installer\EnvironmentDetector;

beforeEach(function () {
    $this->tempDir = sys_get_temp_dir() . '/craft-mcp-clients-tests-' . uniqid();
    mkdir($this->tempDir, 0755, true);
    $this->envDetector = new EnvironmentDetector($this->tempDir);
});

afterEach(function () {
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($this->tempDir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    );

    foreach ($iterator as $item) {
        $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }

    rmdir($this->tempDir);
});

describe('MCP Installer Clients', function () {
    it('generates DDEV wrapper configs for Claude Code, Cursor, and Antigravity', function () {
        $claudeCode = new ClaudeCode($this->tempDir, $this->tempDir, $this->envDetector);
        $cursor = new Cursor($this->tempDir, $this->tempDir, $this->envDetector);
        $antigravity = new Antigravity($this->tempDir, $this->tempDir, $this->envDetector);

        expect($claudeCode->generateServerConfig(EnvironmentDetector::DDEV))->toBe([
            'command' => '${CLAUDE_PROJECT_DIR:-.}/bin/craft-mcp-server',
        ]);

        expect($cursor->generateServerConfig(EnvironmentDetector::DDEV))->toBe([
            'command' => '${workspaceFolder}/bin/craft-mcp-server',
        ]);

        expect($antigravity->generateServerConfig(EnvironmentDetector::DDEV))->toBe([
            'command' => './bin/craft-mcp-server',
        ]);
    });

    it('renders a portable DDEV wrapper script without hardcoded project paths', function () {
        $antigravity = new Antigravity($this->tempDir, $this->tempDir, $this->envDetector);
        $script = $antigravity->renderDdevWrapper();

        expect($script)
            ->toContain('#!/usr/bin/env bash')
            ->toContain('/home/linuxbrew/.linuxbrew/bin/ddev')
            ->toContain('exec "$ddev" exec php "')
            ->not->toContain($this->tempDir);
    });

    it('writes Antigravity plugin manifest and MCP config', function () {
        $antigravity = new Antigravity($this->tempDir, $this->tempDir, $this->envDetector);
        $writer = new ConfigWriter();

        $manifestResult = $writer->writeJson(
            $antigravity->getPluginManifestPath(),
            $antigravity->getPluginManifest(),
        );
        $configResult = $writer->write(
            $antigravity->getConfigPath(),
            'craft-cms',
            $antigravity->generateServerConfig(EnvironmentDetector::DDEV),
        );
        $wrapperResult = $writer->writeExecutable(
            $this->tempDir . '/' . Base::WRAPPER_RELATIVE_PATH,
            $antigravity->renderDdevWrapper(),
        );

        expect($manifestResult->created)->toBeTrue()
            ->and($configResult->created)->toBeTrue()
            ->and($wrapperResult->created)->toBeTrue()
            ->and(is_executable($this->tempDir . '/' . Base::WRAPPER_RELATIVE_PATH))->toBeTrue();

        $manifest = json_decode((string) file_get_contents($antigravity->getPluginManifestPath()), true);
        $mcpConfig = json_decode((string) file_get_contents($antigravity->getConfigPath()), true);

        expect($manifest['name'])->toBe('craft-cms')
            ->and($mcpConfig['mcpServers']['craft-cms']['command'])->toBe('./bin/craft-mcp-server');
    });
});
