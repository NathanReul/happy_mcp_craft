<?php

declare(strict_types=1);

namespace stimmt\craft\Mcp\installer\clients;

use Craft;
use InvalidArgumentException;
use stimmt\craft\Mcp\installer\contracts\Client;
use stimmt\craft\Mcp\installer\EnvironmentDetector;

/**
 * Base class for MCP client implementations.
 *
 * Provides shared configuration generation logic for DDEV and native PHP environments.
 */
abstract class Base implements Client {
    /**
     * Fallback for when the server script cannot be located relative to the
     * Craft root (symlinked checkout, unusual layout): the conventional spot
     * for a dropped-in module.
     */
    protected const BIN_PATH = 'modules/mcp/bin/mcp-server';

    public function __construct(
        protected readonly string $projectRoot,
        protected readonly string $configRoot,
        protected readonly EnvironmentDetector $envDetector,
    ) {
    }

    public function requiresAbsolutePaths(): bool {
        return false;
    }

    public function generateServerConfig(string $environment, ?string $projectPath = null, ?string $craftSubdirectory = null): array {
        return match ($environment) {
            EnvironmentDetector::DDEV => $this->buildDdevConfig($projectPath, $craftSubdirectory),
            EnvironmentDetector::NATIVE => $this->buildNativeConfig($projectPath, $craftSubdirectory),
            default => throw new InvalidArgumentException("Unknown environment: {$environment}"),
        };
    }

    /**
     * Build configuration for DDEV environment.
     *
     * @param string|null $projectPath Absolute project path (for clients that need it)
     * @param string|null $craftSubdirectory Relative path from project root to Craft root
     * @return array{command: string, args: string[], cwd?: string}
     */
    protected function buildDdevConfig(?string $projectPath, ?string $craftSubdirectory): array {
        $config = [
            'command' => $this->getDdevCommand($projectPath),
            'args' => ['exec', 'php', $this->getBinPath($craftSubdirectory)],
        ];

        if ($projectPath !== null && $this->requiresAbsolutePaths()) {
            $config['cwd'] = $projectPath;
        }

        return $config;
    }

    /**
     * Build configuration for native PHP environment.
     *
     * @param string|null $projectPath Absolute project path (for clients that need it)
     * @param string|null $craftSubdirectory Relative path from project root to Craft root
     * @return array{command: string, args: string[], cwd?: string}
     */
    protected function buildNativeConfig(?string $projectPath, ?string $craftSubdirectory): array {
        $config = [
            'command' => $this->getPhpCommand($projectPath),
            'args' => [$this->getBinPath($craftSubdirectory)],
        ];

        if ($projectPath !== null && $this->requiresAbsolutePaths()) {
            $config['cwd'] = $projectPath;
        }

        return $config;
    }

    /**
     * Get the MCP server binary path, prefixed with subdirectory when applicable.
     */
    private function getBinPath(?string $craftSubdirectory): string {
        $binPath = self::relativeBinPath();

        if ($craftSubdirectory === null) {
            return $binPath;
        }

        return $craftSubdirectory . '/' . $binPath;
    }

    /**
     * Where bin/mcp-server actually sits, relative to the Craft root, worked
     * out from where this file sits. As a module this code can be dropped
     * anywhere under the project, so a hard-coded package path would hand
     * every client a script that is not there.
     */
    private static function relativeBinPath(): string {
        $script = self::normalize(dirname(__DIR__, 3) . '/bin/mcp-server');
        $craftRoot = Craft::getAlias('@root', false);

        if (!is_string($craftRoot) || $craftRoot === '') {
            return self::BIN_PATH;
        }

        $craftRoot = rtrim(self::normalize($craftRoot), '/');

        if (!str_starts_with($script, $craftRoot . '/')) {
            return self::BIN_PATH;
        }

        return substr($script, strlen($craftRoot) + 1);
    }

    /**
     * Real path where the target exists, forward slashes either way: the
     * comparison above has to hold on Windows and through symlinks too.
     */
    private static function normalize(string $path): string {
        return strtr(realpath($path) ?: $path, DIRECTORY_SEPARATOR, '/');
    }

    /**
     * Get the ddev command path based on context.
     */
    private function getDdevCommand(?string $projectPath): string {
        if (!$this->shouldUseAbsolutePaths($projectPath)) {
            return 'ddev';
        }

        return $this->envDetector->getDdevBinaryPath() ?? 'ddev';
    }

    /**
     * Get the php command path based on context.
     */
    private function getPhpCommand(?string $projectPath): string {
        if (!$this->shouldUseAbsolutePaths($projectPath)) {
            return 'php';
        }

        return $this->envDetector->getPhpBinaryPath() ?? 'php';
    }

    /**
     * Check if absolute paths should be used.
     */
    private function shouldUseAbsolutePaths(?string $projectPath): bool {
        return $projectPath !== null && $this->requiresAbsolutePaths();
    }
}
