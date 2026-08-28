# Install Craft MCP

Craft MCP is a plain **Craft module** in a plain **Composer package**: MIT licensed, no
plugin store entry, no license key, no plugin lifecycle. Pick one of the three delivery
routes below, then do steps 2-5, which are the same either way.

Everything runs from your **Craft root** (the directory holding `composer.json`, `config/`
and the `craft` executable).

## 1. Get the code into the project

### Route A - Composer package from your own Git repo (recommended)

Push this repository to your own GitHub/GitLab, tag a release, then:

```bash
composer config repositories.craft-mcp vcs https://github.com/<you>/craft-mcp.git
composer require happy-horizon/craft-mcp:^1.0
```

Nothing lands in your project's Git tree, the exact commit is pinned in `composer.lock`,
and any pipeline that already runs `composer install` deploys it with no extra steps.
Updates are `composer update happy-horizon/craft-mcp`. Private repos work too; the deploy
machine needs a token in its Composer auth config.

Published to Packagist instead? Then it is just `composer require happy-horizon/craft-mcp`
with no `repositories` line at all.

### Route B - Copied into the project

Copy the directory to `modules/mcp` (any path inside the project works), then point
Composer at it so the dependencies and autoloading are still handled:

```bash
composer config repositories.craft-mcp path modules/mcp
composer require happy-horizon/craft-mcp:*
```

Composer links `modules/mcp` into `vendor/`, so your edits are live and `composer update`
never replaces it. `*` means "whatever version that folder declares". If you copied the
files without their Git history, Composer has no version to read: add
`"version": "1.0.0"` to the module's own `composer.json`.

A Git submodule at `modules/mcp` is the same route with upstream tracking added. Watch the
deploy: a plain `git clone` does not fetch submodules, and an empty `modules/mcp` is a
white screen, not a degraded site.

### Route C - No Composer at all

Possible, but you take over two jobs Composer was doing. Add the namespace to your
project's `composer.json`:

```json
{
    "autoload": {
        "psr-4": {
            "stimmt\\craft\\Mcp\\": "modules/mcp/src/"
        }
    }
}
```

and install the module's dependencies yourself:

```bash
composer require mcp/sdk psy/psysh nyholm/psr7 psr/event-dispatcher psr/simple-cache symfony/finder symfony/polyfill-php84
composer dump-autoload
```

There is no way around this part: PHP has one autoloader and one `vendor/` per project, so
the module's dependencies must resolve into the same tree as Craft's. A nested
`vendor/autoload.php` inside the module would load a second copy of Craft's own classes.

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
Claude Desktop) and writes their config files. The server script path it writes is
resolved from wherever the module actually ended up, so all three routes above produce a
working config. Manual configuration: [docs/client-setup.md](docs/client-setup.md).

For remote users over HTTP instead of stdio, see
[docs/http-transport.md](docs/http-transport.md).

## Updating

Route A: `composer update happy-horizon/craft-mcp`. Routes B and C: replace the directory.
Either way, afterwards:

```bash
php craft mcp/setup
php craft clear-caches/all
```

## Trimming it down

Only `src/`, `bin/`, `docs/` (served by the `craft://guides/*` MCP resources) and
`composer.json` are needed at runtime. `tests/`, `.github/`, `phpstan.neon`, `phpunit.xml`,
`pint.json`, `rector.php`, `.lefthook.yml` and `meta.json` are development scaffolding and
can be deleted, as can the `require-dev` and `scripts` blocks in `composer.json`.

## What changed from the plugin version

- `Mcp` extends `yii\base\Module` instead of `craft\base\Plugin`, and wires the things a
  plugin gets for free: controller namespaces, the `mcp` translation category, the control
  panel template root, and its own version.
- Settings no longer live in project config. Defaults, then any `settings` array from
  `config/app.php`, then `config/mcp.php` - with the production safety defaults applied
  before all of them, so only an explicit key can lift them.
- `php craft mcp/setup` replaces `php craft plugin/install mcp`.
- The package is `happy-horizon/craft-mcp`, `type: library`, so it can be published or
  required from a VCS repo without colliding with upstream's `stimmt/craft-mcp` on
  Packagist. Client configs point at the module's real location rather than a fixed
  `vendor/` path.
