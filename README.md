<p align="center">
  <img src="src/icon.svg" alt="Craft MCP" width="100" height="100">
</p>

<p align="center">
  <a href="https://packagist.org/packages/happy-horizon/craft-mcp"><img src="https://img.shields.io/packagist/v/happy-horizon/craft-mcp.svg" alt="Packagist"></a>
  <a href="LICENSE"><img src="https://img.shields.io/badge/license-MIT-green.svg" alt="License: MIT"></a>
  <a href="https://github.com/stimmtdigital/craft-mcp"><img src="https://img.shields.io/badge/fork%20of-stimmt%2Fcraft--mcp-blue.svg" alt="Fork of stimmt/craft-mcp"></a>
</p>

## Introduction

Craft MCP is an MCP (Model Context Protocol) server for Craft CMS. It gives AI assistants direct access to your installation's content, schema, and configuration: rather than describing your field layouts or Matrix setups by hand, the assistant queries them, writes reviewable draft content in a natural-key format, and inspects the system it is working in.

It ships more than 50 specialized tools, 10 analysis prompts, and 13 data resources, served over stdio for local development and over HTTP with scoped bearer tokens for remote users.

## Quick Start

Craft MCP is a Craft **module** in a plain Composer package - no plugin store, no license key, no plugin lifecycle. Requires Craft CMS 5.1+ and PHP 8.3+.

From your Craft root, require it from Packagist:

```bash
composer require happy-horizon/craft-mcp:dev-main
```

> The `main` branch is the only thing published so far, hence `dev-main`. Composer sets the stability flag for you when the constraint is explicit, so your project's `minimum-stability` is untouched. Switch to `composer require happy-horizon/craft-mcp` once a release is tagged.

Register it in `config/app.php`:

```php
<?php

use stimmt\craft\Mcp\Mcp;

return [
    'modules' => ['mcp' => Mcp::class],
    'bootstrap' => ['mcp'],
];
```

Create its tables (idempotent, safe to re-run after updates):

```bash
php craft mcp/setup
```

Enable the server in `config/mcp.php` (it is disabled in production by default):

```php
<?php

return [
    'enabled' => true,
];
```

Then run the interactive wizard to configure your MCP clients:

```bash
php craft mcp/install
```

That is the whole happy path. Full install steps, including forks, in-project checkouts, updating, and troubleshooting, are in [INSTALL.md](INSTALL.md). For manual client configuration (Claude Code, Cursor, Claude Desktop, SSH), see the [Client Setup guide](docs/client-setup.md); for all options including tool disabling, IP allowlists, and environment defaults, see the [Configuration guide](docs/configuration.md).

## Installation Prompt

Rather have an assistant do it? Paste this into Claude Code, Cursor, or any coding agent with shell access to your Craft project:

````text
Install the Craft MCP module (https://packagist.org/packages/happy-horizon/craft-mcp) into
this Craft CMS project. Work from the Craft root - the directory holding composer.json,
config/ and the craft executable. Verify Craft is 5.1+ and PHP is 8.3+ before starting, and
stop and tell me if either is too old.

1. Require the package. There is no stable tag on Packagist yet, so the constraint must be
   explicit:

       composer require happy-horizon/craft-mcp:dev-main

   If Composer asks to allow the php-http/discovery plugin, allow it - it is part of the
   PSR-7 stack the MCP SDK uses.

2. Register the module in config/app.php. Merge into the existing return array rather than
   overwriting it, and keep any modules that are already registered:

       use stimmt\craft\Mcp\Mcp;

       return [
           'modules' => ['mcp' => Mcp::class],
           'bootstrap' => ['mcp'],
       ];

   The module id must stay 'mcp' (routes, templates, console commands and translations are
   keyed to it) and 'mcp' must be in 'bootstrap', not just 'modules'.

3. Run the migrations:

       php craft mcp/setup

   Prefix with `ddev` if this project uses DDEV.

4. Create or update config/mcp.php so MCP is on in development and off in production:

       return [
           '*' => ['enabled' => false],
           'dev' => ['enabled' => true, 'enableDangerousTools' => true],
       ];

5. Run `php craft mcp/install` to write the client config. It is an interactive wizard - if
   you cannot answer prompts, tell me to run it myself instead of guessing, and point me at
   docs/client-setup.md in the installed package for the manual config.

Then confirm the install: `php craft mcp/setup` should report MCP as enabled. Report back
what you changed, what the wizard wrote, and anything you skipped.
````

## Remote Access over HTTP

Content editors and office users can point Claude Desktop straight at a remote install: the module serves the MCP protocol from a Craft endpoint, authenticated with per-user bearer tokens scoped to `readonly`, `content`, or `full` access. Off by default; enabling it, minting tokens (`php craft mcp/tokens/create`), and troubleshooting are covered in the [HTTP Transport guide](docs/http-transport.md).

## Content Writing for Agents

Entry reads and writes share one payload format: relations as natural keys (`{"section": "pages", "slug": "about"}`), Matrix blocks by type handle, and per-field `input` shapes from `describe_entry_schema` that tell an agent exactly what every field accepts, third-party fields included. Writes land as reviewable drafts with a control panel deep link, and `publish_entry` makes them live. The full format, workflow, and schema discovery are covered in the [Content Writing guide](docs/content-writing.md).

## The Toolbox

| Category | Highlights |
|----------|-----------|
| [Content](docs/tools/content.md) | Entries (payload-format read/write, field/relation/date filters, count and group-by breakdowns, draft workflow with a pending-drafts review queue and revision history, publish/duplicate/copy to site), schema discovery via `describe_entry_schema`, assets, categories, users, globals |
| [System](docs/tools/system.md) | System info, config, logs, caches, routes, console commands |
| [Database](docs/tools/database.md) | Schema inspection, table counts, read-only queries |
| [Debugging](docs/tools/debugging.md) | Queue jobs, project config diff, deprecations, EXPLAIN, event handlers, and a Tinker tool for executing PHP in the Craft context |
| [Multi-Site](docs/tools/multisite.md) | Sites, site groups, per-site details |
| [GraphQL](docs/tools/graphql.md) | Schemas, SDL, read-only and mutating query execution, tokens |
| [Backup](docs/tools/backup.md) | Create and list database backups |
| [Self-Awareness](docs/tools/mcp.md) | Module info, tool listing with risk annotations, hot reload |
| [Commerce](docs/tools/commerce.md) | Products, orders, and statuses (when Craft Commerce is installed) |

Tools that modify data or execute code are flagged `dangerous`: they sit behind the `enableDangerousTools` setting, carry a `destructiveHint` annotation in `tools/list`, and are excluded from `readonly` and (except entry workflow) `content` HTTP scopes. See the [Tools Overview](docs/tools/README.md) for the complete reference.

## Extending

Other plugins and modules can register their own tools, prompts, resources, and field translators through events (`EVENT_REGISTER_TOOLS`, `EVENT_REGISTER_FIELD_TRANSLATORS`, and friends). See the [Extending guide](docs/extending.md) for implementation details and examples.

## Documentation

The [documentation index](docs/README.md) links everything; the direct routes:

- **[Installation](INSTALL.md)** - Requirements, Composer setup, detailed installation steps
- **[Client Setup](docs/client-setup.md)** - Wizard and manual configs for Claude Code, Cursor, Claude Desktop
- **[Configuration](docs/configuration.md)** - All configuration options and security settings
- **[HTTP Transport](docs/http-transport.md)** - Remote access with per-user scoped bearer tokens
- **[Content Writing](docs/content-writing.md)** - The payload format, draft workflow, and schema discovery for agents
- **[Tools Reference](docs/tools/README.md)** - Complete documentation for every tool
- **[Prompts](docs/prompts.md)** - Pre-built analysis prompts
- **[Resources](docs/resources.md)** - Read-only URI-based access to schema, config, and content data
- **[Extending](docs/extending.md)** - Register custom tools, prompts, resources, and field translators

## Contributing

Thank you for considering contributing to Craft MCP! Please use [GitHub Issues](https://github.com/NathanReul/happy_mcp_craft/issues) on this fork for bug reports, feature requests, and discussion.

## Credits

- Created and maintained by [Max van Essen](https://github.com/vanEssenMax)
- Packaged as a Craft module and distributed by [Happy Horizon](https://github.com/NathanReul/happy_mcp_craft)
- Inspired by [Laravel Boost](https://github.com/laravel/boost)
- Icon from [Lucide](https://lucide.dev) (MIT)

## License

Craft MCP is open-sourced software licensed under the [MIT license](LICENSE).
