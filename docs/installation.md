# Installation

This guide walks you through installing Craft MCP and connecting it to your preferred AI assistant.

## Requirements

Before installing Craft MCP, ensure your environment meets these requirements:

- **Craft CMS** 5.0 or later
- **PHP** 8.3 or later

The module is compatible with all database drivers supported by Craft CMS (MySQL, PostgreSQL).

## Install the Module

Craft MCP is a plain Craft module in a plain Composer package: no plugin store, no license key, no plugin lifecycle.

Two ways in. If the code lives in your own Git repo (or on Packagist), require it as a Composer package and skip straight to step 3:

```bash
composer config repositories.craft-mcp vcs https://github.com/<you>/craft-mcp.git
composer require happy-horizon/craft-mcp:^1.0
```

Otherwise copy it into the project, as steps 1 and 2 describe. Both routes in full: [INSTALL.md](../INSTALL.md).

### 1. Copy the code

Put this directory at `modules/mcp` in your Craft project. Any path works; `modules/` is the Craft convention.

```bash
# from your Craft root
git clone https://github.com/stimmtdigital/craft-mcp.git modules/mcp
rm -rf modules/mcp/.git
```

Or simply copy the folder there.

### 2. Autoload it and pull in its dependencies

The module has Composer dependencies of its own (the MCP SDK, PsySH, a PSR-7 implementation), so it cannot be autoloaded by copying alone. A path repository handles autoloading and dependencies in one step:

```bash
# from your Craft root
composer config repositories.craft-mcp path modules/mcp
composer require happy-horizon/craft-mcp:*
```

Composer symlinks `modules/mcp` into `vendor/`, so edits to the module take effect immediately, and `composer update` never overwrites it with a Packagist release.

Prefer not to use a path repository? Wire it by hand in your project's `composer.json`:

```json
{
    "autoload": {
        "psr-4": {
            "stimmt\\craft\\Mcp\\": "modules/mcp/src/"
        }
    }
}
```

```bash
composer require mcp/sdk psy/psysh nyholm/psr7 psr/event-dispatcher psr/simple-cache symfony/finder symfony/polyfill-php84
composer dump-autoload
```

### 3. Register the module

In `config/app.php`:

```php
<?php

use stimmt\craft\Mcp\Mcp;

return [
    'modules' => [
        'mcp' => Mcp::class,
    ],
    'bootstrap' => ['mcp'],
];
```

The `bootstrap` entry is required rather than optional: it is what loads the module on every web and console request, which is what makes the console commands, the HTTP endpoint and the control panel screens exist at all.

Keep the module id as `mcp`. Routes, control panel templates, console commands and the translation category all hang off it.

Settings can be passed inline here if you prefer that to a config file, though `config/mcp.php` still wins over anything set this way:

```php
'modules' => [
    'mcp' => [
        'class' => Mcp::class,
        'settings' => ['enabled' => true],
    ],
],
```

### 4. Create the database tables

A plugin gets its tables from Craft's install lifecycle; a module has no such lifecycle, so run the migrations yourself:

```bash
php craft mcp/setup
```

The command is idempotent: run it again after updating the module and it applies whatever is new.

## Verify Installation

```bash
php craft mcp/setup   # reports whether MCP is enabled and how it is reachable
php craft help mcp    # lists the module's console commands
```

If `php craft help mcp` reports no such command, the module is not being loaded: check the `modules` and `bootstrap` keys in `config/app.php`, and that `stimmt\craft\Mcp\Mcp` is autoloadable (`composer dump-autoload`).

## Enable the MCP Server

By default, the MCP server is disabled for security. Create a configuration file at `config/mcp.php` to enable it:

```php
<?php

return [
    'enabled' => true,
];
```

For production environments, you'll want to review the security options in the [Configuration Guide](configuration.md), including IP allowlists and tool restrictions.

## MCP Client Setup

Once the module is registered and enabled, connect your AI assistant. The quickest route is the built-in wizard:

```bash
php craft mcp/install
```

The wizard detects your environment (DDEV or native PHP), lets you pick clients (Claude Code, Cursor, Claude Desktop), and writes the config files for you.

Wizard options, manual configuration for every client, and the SSH variant live in the [Client Setup guide](client-setup.md). Remote users without a local checkout connect through the [HTTP transport](http-transport.md) instead.

## Troubleshooting

### Server not starting

If the MCP server fails to start, check the following:

1. **Module is loaded**: Run `php craft help mcp` and verify the commands are listed
2. **Configuration exists**: Ensure `config/mcp.php` exists and contains `'enabled' => true`
3. **PHP path is correct**: For Claude Desktop, verify your absolute paths are correct using `which php` or `which ddev`
4. **DDEV is running**: If using DDEV, ensure your containers are up with `ddev start`

### Tools not appearing

If the MCP server connects but tools aren't showing up:

1. **Check for errors**: Look at your Craft logs in `storage/logs/` for any MCP errors
2. **Verify PHP version**: Ensure you're running PHP 8.3 or later

## Next Steps

Now that Craft MCP is installed and connected, you may want to:

1. **[Configure the plugin](configuration.md)** - Review security settings and customize which tools are available
2. **[Explore available tools](tools/README.md)** - Learn what your AI assistant can now do with your Craft installation
