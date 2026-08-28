# Install Craft MCP

Craft MCP is a plain **Craft module** in a plain **Composer package**: MIT licensed, no
plugin store entry, no license key, no plugin lifecycle. It is published on Packagist as
[`happy-horizon/craft-mcp`](https://packagist.org/packages/happy-horizon/craft-mcp), so
installing it is `composer require` plus four short steps.

Everything below runs from your **Craft root** (the directory holding `composer.json`,
`config/` and the `craft` executable).

Requirements: Craft CMS 5.1+, PHP 8.3+.

## 1. Require the package

```bash
composer require happy-horizon/craft-mcp:dev-main
```

> **Why `dev-main`?** The package currently publishes the `main` branch only - there is no
> stable tag on Packagist yet. Composer adds the stability flag for you when the constraint
> is explicit like this, so your project's `minimum-stability` does not need touching. Once
> a release is tagged, `composer require happy-horizon/craft-mcp` is the constraint to use,
> and the one to switch to.

Nothing lands in your project's Git tree, the exact commit is pinned in `composer.lock`,
and any pipeline that already runs `composer install` deploys it with no extra steps.
Updates are `composer update happy-horizon/craft-mcp`.

Composer may ask to allow the `php-http/discovery` plugin the first time; it is part of the
PSR-7 stack the MCP SDK uses. Answering yes writes it into your project's
`config.allow-plugins`, alongside the entries Craft already put there.

### Alternative: a fork of your own

Working on the module itself, or running a private fork? Point Composer at your repository
instead of Packagist and require it the same way:

```bash
composer config repositories.craft-mcp vcs https://github.com/<you>/craft-mcp.git
composer require happy-horizon/craft-mcp:dev-main
```

The `repositories` entry takes precedence over Packagist for that package name. Private
repos work too; the deploy machine needs a token in its Composer auth config.

### Alternative: a checkout inside the project

To edit the module in place, put the checkout somewhere in the project (`modules/mcp` is
the Craft convention) and register it as a **path** repository, so Composer still resolves
its dependencies and autoloading:

```bash
composer config repositories.craft-mcp path modules/mcp
composer require happy-horizon/craft-mcp:*
```

Composer symlinks `modules/mcp` into `vendor/`, so your edits are live and `composer update`
never replaces it with a Packagist release. `*` means "whatever version that folder
declares"; if you copied the files without their Git history, Composer has no version to
read, so add `"version": "1.4.0"` to the module's own `composer.json`.

A Git submodule at `modules/mcp` is the same route with upstream tracking added. Watch the
deploy: a plain `git clone` does not fetch submodules, and an empty `modules/mcp` is a
white screen, not a degraded site.

There is no supported route without Composer. PHP has one autoloader and one `vendor/` per
project, so the module's dependencies (the MCP SDK, PsySH, a PSR-7 implementation) have to
resolve into the same tree as Craft's - a nested `vendor/autoload.php` inside the module
would load a second copy of Craft's own classes.

## 2. Register the module

`config/app.php`:

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

Two things matter here:

- **`bootstrap` is required.** It is what loads the module on every web and console
  request, which is what makes the console commands, the HTTP endpoint and the control
  panel screens exist at all.
- **Keep the id `mcp`.** Routes, control panel templates, console commands and the
  translation category are all keyed to it. Register it under another id and the module
  logs a warning and half of it goes missing.

Settings can be passed inline if you prefer that to a config file, though `config/mcp.php`
still wins over anything set this way:

```php
'modules' => [
    'mcp' => [
        'class' => Mcp::class,
        'settings' => ['enabled' => true],
    ],
],
```

## 3. Create the database tables

A plugin gets its tables from Craft's install lifecycle; a module has none, so run the
migrations yourself:

```bash
php craft mcp/setup
```

Idempotent: run it again after updating and it applies whatever is new. It also prints
whether MCP is currently enabled and how it is reachable.

## 4. Enable it

MCP is **disabled in production by default**, and dangerous tools with it. Turn it on in
`config/mcp.php`:

```php
<?php

return [
    'enabled' => true,
];
```

Craft's multi-environment convention works here too:

```php
<?php

return [
    '*' => ['enabled' => false],
    'dev' => ['enabled' => true, 'enableDangerousTools' => true],
];
```

All options: [docs/configuration.md](docs/configuration.md).

## 5. Point a client at it

```bash
php craft mcp/install
```

The wizard detects DDEV or native PHP, asks which clients you use (Claude Code, Cursor,
Claude Desktop) and writes their config files. The server script path it writes is resolved
from wherever the module actually ended up, so every install route above produces a working
config. Manual configuration: [docs/client-setup.md](docs/client-setup.md).

For remote users over HTTP instead of stdio, see
[docs/http-transport.md](docs/http-transport.md).

## Updating

```bash
composer update happy-horizon/craft-mcp
php craft mcp/setup
php craft clear-caches/all
```

On a path-repository checkout, pull the checkout instead of running `composer update`, then
run the same two `craft` commands.

## Uninstalling

```bash
composer remove happy-horizon/craft-mcp
```

Then drop the `mcp` entries from `config/app.php` and delete `config/mcp.php`. The module's
own tables are left behind; remove them by hand if you want them gone.

## Troubleshooting

- **`Unknown command: mcp/setup`** - the module is not bootstrapped. Check that `'mcp'` is
  in the `bootstrap` array in `config/app.php`, not just in `modules`.
- **`Class "stimmt\craft\Mcp\Mcp" not found`** - the package is not installed in this
  project's `vendor/`. Re-run the `composer require` from the Craft root and check that
  `vendor/happy-horizon/craft-mcp` exists.
- **`Could not find a version of package happy-horizon/craft-mcp matching your
  minimum-stability`** - the `:dev-main` constraint was dropped. There is no stable tag
  yet; keep it until one is published.
- **The client connects but lists no tools** - MCP is disabled for this environment. Set
  `'enabled' => true` in `config/mcp.php`, then `php craft clear-caches/all`.

## Trimming it down

Only `src/`, `bin/`, `docs/` (served by the `craft://guides/*` MCP resources) and
`composer.json` are needed at runtime. `tests/`, `.github/`, `phpstan.neon`, `phpunit.xml`,
`pint.json`, `rector.php`, `.lefthook.yml` and `meta.json` are development scaffolding and
can be deleted, as can the `require-dev` and `scripts` blocks in `composer.json`. On a
normal Composer install this is not something you need to do.

## What changed from the plugin version

- `Mcp` extends `yii\base\Module` instead of `craft\base\Plugin`, and wires the things a
  plugin gets for free: controller namespaces, the `mcp` translation category, the control
  panel template root, and its own version.
- Settings no longer live in project config. Defaults, then any `settings` array from
  `config/app.php`, then `config/mcp.php` - with the production safety defaults applied
  before all of them, so only an explicit key can lift them.
- `php craft mcp/setup` replaces `php craft plugin/install mcp`.
- The package is `happy-horizon/craft-mcp`, `type: library`, so it installs as an ordinary
  Composer dependency without colliding with upstream's `stimmt/craft-mcp` on Packagist.
  Client configs point at the module's real location rather than a fixed `vendor/` path.
