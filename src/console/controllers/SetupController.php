<?php

declare(strict_types=1);

namespace stimmt\craft\Mcp\console\controllers;

use Craft;
use craft\console\Controller;
use craft\db\MigrationManager;
use craft\helpers\Console;
use stimmt\craft\Mcp\Mcp;
use stimmt\craft\Mcp\migrations\Install;
use Throwable;
use yii\console\ExitCode;

/**
 * One-time setup for the MCP module.
 *
 * A plugin gets its tables from Craft's install lifecycle; a module has no
 * such lifecycle, so the same migrations are applied from here. Everything
 * it does is idempotent: run it again after updating the module and it
 * applies whatever is new and leaves the rest alone.
 *
 * Usage:
 *   php craft mcp/setup
 *
 * @author Max van Essen <support@stimmt.digital>
 */
class SetupController extends Controller {
    /**
     * Create (or update) the MCP database tables.
     */
    public function actionIndex(): int {
        $this->stdout("Setting up Craft MCP\n\n", Console::BOLD);

        if (Mcp::getInstance() === null) {
            $this->stderr("The MCP module is not loaded.\n", Console::FG_RED);
            $this->stderr("Add it to the 'modules' and 'bootstrap' keys of config/app.php, then run this again.\n");

            return ExitCode::CONFIG;
        }

        if (!$this->applyMigrations()) {
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $this->stdout("Database tables are up to date.\n", Console::FG_GREEN);
        $this->reportState();

        return ExitCode::OK;
    }

    /**
     * Prefer Craft's migration manager, so applied migrations are recorded
     * under the module's own track and `migrate/up --track=module:mcp`
     * keeps working. If that API is not available on this Craft version,
     * fall back to applying the schema directly; every migration here is
     * guarded by a table-exists check, so replaying it is harmless.
     */
    private function applyMigrations(): bool {
        try {
            $manager = Craft::createObject([
                'class' => MigrationManager::class,
                'track' => 'module:' . Mcp::HANDLE,
                'migrationNamespace' => 'stimmt\craft\Mcp\migrations',
                'migrationPath' => dirname(__DIR__, 2) . '/migrations',
            ]);

            /** @var MigrationManager $manager */
            $manager->up();

            return true;
        } catch (Throwable $migrationManagerError) {
            $this->stdout("Migration track unavailable ({$migrationManagerError->getMessage()}); applying schema directly.\n", Console::FG_YELLOW);
        }

        try {
            (new Install())->safeUp();

            return true;
        } catch (Throwable $e) {
            $this->stderr("Setup failed: {$e->getMessage()}\n", Console::FG_RED);

            return false;
        }
    }

    /**
     * The two questions asked immediately after setup: is it on, and can
     * anything reach it.
     */
    private function reportState(): void {
        $settings = Mcp::settings();

        $this->stdout("\nEnvironment:    " . (Craft::$app->env ?? 'unknown') . "\n");
        $this->stdout('MCP enabled:    ' . ($settings->enabled ? 'yes' : 'no') . "\n");
        $this->stdout('HTTP transport: ' . ($settings->httpTransport ? 'yes (/' . $settings->httpPath . ')' : 'no') . "\n");

        if (!$settings->enabled) {
            $this->stdout("\nMCP is disabled in this environment. Enable it in config/mcp.php:\n", Console::FG_YELLOW);
            $this->stdout("  <?php return ['enabled' => true];\n");
        }

        $this->stdout("\nNext: php craft mcp/install    (writes MCP client config)\n");
    }
}
