# AGENTS.md

## Overview

Apiunto is a MediaWiki extension (requires MW 1.43+, PHP 8.1+) that exposes a Scribunto (Lua) library for fetching data from configured REST APIs. PHP handles request execution, response caching via `WANObjectCache`, and cache purging; Lua templates call `mw.ext.Apiunto.fetch( source, endpoint )` to consume the data. Scribunto is a hard dependency.

Key pieces:

- `src/ApiuntoLuaLibrary.php` — registers Lua callbacks (entry point from Scribunto)
- `src/Repositories/` — HTTP fetch + cache layer
- `src/Services/CachePurger.php` — invalidates cached responses
- `src/Hooks/` — `ArticlePurge`, `InfoAction`, and `ScribuntoExternalLibraries` hook handlers
- `maintenance/purgeCache.php` — CLI script for bulk cache purging
- API sources are configured via `$wgApiuntoSources` in `LocalSettings.php`

## Verification

Run only what's relevant to the files you changed.

| Files changed | Command |
| --- | --- |
| `*.php` | `composer preflight` (lint, style, and Phan) |
| `i18n/` | `composer test` (PHP lints cover JSON parse errors indirectly; otherwise check manually) |

Auto-fix command: `composer fix` (PHP).

**Preflight**: Run `composer preflight` from within a MediaWiki installation to execute all PHP lints, style checks, and Phan static analysis.

**Always run the relevant checks before committing.** Read the full output — PHPCS warnings must be fixed, not just errors. The command exits 0 even with warnings, so do not treat exit code alone as a pass.

### Dev environment

This project's standard dev environment is the MediaWiki Docker setup defined in the parent `mediawiki/` directory. The user may be using a different environment. Ask the user for their dev environment URL and how to run commands if not already known.

To run composer commands in the standard Docker environment:

```sh
docker compose exec mediawiki bash -c "cd /var/www/html/w/extensions/Apiunto && composer preflight"
```

### Phan

Phan requires a full MediaWiki installation at `../../` for type resolution. Because Scribunto is a hard dependency, `.phan/config.php` adds it to the directory list when present.

```sh
docker compose exec mediawiki bash -c "cd /var/www/html/w/extensions/Apiunto && composer phan"
```

## Coding conventions

### PHP

- New files should start with `declare( strict_types=1 );`
- Use native PHP types (properties, parameters, return values); use PHPDoc only for collection types like `string[]`
- Always use MediaWiki-namespaced imports (`use MediaWiki\Title\Title;`), never legacy shims (`use Title;`)
- Scribunto imports use the `MediaWiki\Extension\Scribunto\Engines\LuaCommon\*` namespace

### Lua

- The shipped library is `src/mw.ext.Apiunto.lua`
- `.editorconfig` defines the project's Lua style (EmmyLuaCodeStyle); `.luarc.json` configures the language server

### extension.json

`extension.json` is the source of truth for how the extension is wired — hooks, config variables, service wiring, and autoload namespaces are all declared here.

- Config variables are declared under `config` in `extension.json` (prefixed `wgApiunto`)
- Service wiring lives in `src/ServiceWiring.php`; services are resolved through `MediaWikiServices`
- New hook handlers must be registered under `HookHandlers` and bound under `Hooks`

### Commits

- Use [Conventional Commits](https://www.conventionalcommits.org/) (e.g. `fix:`, `feat:`, `refactor:`)
- Use `ci:` or `chore:` for non-user-facing changes (tooling, config, dependencies)

### i18n

- Any user-facing string needs a message key in `i18n/en.json`
- Every key in `en.json` must also have a documentation entry in `i18n/qqq.json`
