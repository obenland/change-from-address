# AGENTS.md

This file provides guidance to coding agents working in this repository.

## What this is

A single-file WordPress plugin (`change-from-address.php`) that overrides `wp_mail_from` and `wp_mail_from_name` so site emails use a configured sender name and address. Settings are registered into the core **Settings → General** screen — there is no custom admin page.

All identifiers are prefixed `cefko_` (a legacy prefix predating the "Change From Address" rename — see Changelog). Two options drive everything: `cefko_email_from_name` and `cefko_email_from_address`.

## Commands

Install dev dependencies (PHPCS + WPCS):

```bash
composer install
```

Run coding-standards check (matches CI):

```bash
vendor/bin/phpcs --standard=WordPress --extensions=php --ignore="node_modules,vendor" .
```

Auto-fix what's fixable:

```bash
vendor/bin/phpcbf --standard=WordPress --extensions=php --ignore="node_modules,vendor" .
```

There is no test suite, no build step, and no JS/CSS toolchain.

For a live sandbox, `.wordpress-org/blueprints/blueprint.json` boots the plugin in WordPress Playground (admin/password, lands on the settings section).

## Release flow

The WordPress.org plugin directory is the deployment target — there is no staging/prod app. Two automated paths:

- **Push to `trunk`** → `.github/workflows/push-asset-readme-update.yml` syncs `readme.txt` and `.wordpress-org/` assets to SVN via `10up/action-wordpress-plugin-asset-update`. Code changes are NOT shipped by this workflow.
- **Push a git tag** → `.github/workflows/deploy.yml` ships a full release to SVN via `10up/action-wordpress-plugin-deploy`. To cut a release: bump `Version:` in `change-from-address.php`, bump `Stable tag:` and add a Changelog entry in `readme.txt`, merge to trunk, then tag (the tag name is the version, e.g. `3`).

`Stable tag` in `readme.txt` is the source of truth WordPress.org reads — keep it in sync with the plugin header `Version`.

## "Tested up to" automation

`.github/workflows/update-tested-up-to.yml` runs weekly (Mon 09:00 UTC) and on `workflow_dispatch`. It queries `api.wordpress.org` for the current WP version, bumps `Tested up to:` in `readme.txt`, opens a PR, merges it (immediate when `mergeStateStatus` is `CLEAN`/`UNSTABLE`, auto-merge when `BLOCKED`/`BEHIND`), waits for the merge to land, then resets the workspace to `origin/trunk` before invoking the SVN asset sync. The reset matters: `peter-evans/create-pull-request` leaves the working tree pre-PR, so without it the SVN sync would publish a stale `readme.txt`. Preserve that ordering when editing this workflow.

## Conventions

- WordPress Coding Standards (PHPCS rules in `composer.json`, enforced by `wpcs.yml` on every push).
- Follow the existing comment style: `/** */` docblocks on every function with aligned `@param`/`@return`.
- The `cefko_` prefix is load-bearing — option names and filter callback names are public API for users with existing installs. Don't rename without a migration story.
- `.gitattributes` controls what ships to WordPress.org via `export-ignore` — anything new that shouldn't be in user installs (tests, tooling, CI) needs an entry there.

## PHP version: four sources of truth

Four files declare PHP versions and they don't agree:

- `readme.txt` `Requires PHP:` — the user-facing minimum (currently 7.0)
- `composer.json` `require.php` — what dev dependencies need (currently `^7.2|^8.0`)
- `.github/workflows/wpcs.yml` — the version CI lints against (currently 7.4)
- `.wordpress-org/blueprints/blueprint.json` — Playground runtime (currently 8.2)

`readme.txt` is the contract with users. Bump it deliberately, and update the others when raising the floor.
