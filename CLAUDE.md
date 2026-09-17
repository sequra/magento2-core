# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Authoritative standard — read before writing code

`.claude/docs/codingStandard.md` is the **binding coding standard** for this module and the single source of truth; the summaries elsewhere in this file defer to it. **Read it before writing or changing any code.** It covers the module architecture, the integration-core bridge, the **PHP 7.4 syntax floor**, the Magento2 PHPCS standard, and PHPStan level 9.

**The quality gate (non-negotiable):** a change is done only when it passes, in this order, the PHP 7.4–8.5 syntax sweep, then `./bin/phpcs -q`, then `./bin/phpstan` (after `./bin/update-sequra`) — i.e. **`phpcs` → `phpstan` must both pass**, all via the Docker `bin/` wrappers. Don't silence PHPStan. Three ignore patterns are sanctioned: (1) Magento's auto-generated factories; (2) calls through integration-core's `CheckoutAPI`/`AdminAPI` — these return an `Aspects` proxy whose fluent methods dispatch via `Aspects::__call(): mixed`, so PHPStan can't resolve the concrete controller/return types; and (3) `get`/`set`/`uns`/`has` accessors on the Magento session classes (`Magento\Checkout\Model\Session`, `Magento\Customer\Model\Session`) — these dispatch through `SessionManager::__call` to the underlying `Storage` `DataObject`, so `setData()`/`unsetData()`/`setLastQuoteId()` etc. are runtime-valid but undefined on the concrete class (only `getData()` is explicitly declared, hence unflagged). Both the aspect case and the session case are handled centrally by `phpstan.neon` (`ignoreErrors: '#Call to an undefined method …Aspects::#'` and `'#Call to an undefined method Magento\\(Checkout|Customer)\\Model\\Session::#'`, plus `reportUnmatchedIgnoredErrors: false`); for the aspect case prefer typing the result with `/** @var SomeResponse $response */` (as in `Model/ExpressCheckout/AvailabilityEvaluator.php`) over a per-line `@phpstan-ignore-next-line`. Nothing else is sanctioned. For tests, see `Test/README.md`.

## What this is

`Sequra_Core` — the Magento 2 module (`composer` type `magento2-module`, PSR-4 root `Sequra\Core\`) that integrates SeQura payment methods into a Magento store. The module itself is a thin adapter: nearly all business logic lives in the `sequra/integration-core` Composer package (PSR-4 `SeQura\Core\` — note the capital `Q`). This repo implements the Magento-specific *integration interfaces* that core defines, and wires them in via dependency injection.

**Two namespaces, easy to confuse:**
- `Sequra\Core\...` (lowercase `q`) → this repository.
- `SeQura\Core\...` (capital `Q`) → the `sequra/integration-core` SDK in `vendor/`, where the domain/business logic lives.

## Architecture

See the `architecture` skill for the full map of how the module is wired (bootstrap, persistence, gateway, checkout API, controllers, admin UI, order lifecycle).

Two things that live here because getting them wrong is expensive:

- **Adding an integration service:** implement the core `*ServiceInterface` under `Services/BusinessLogic/`, then register it in `Bootstrap::initServices()`. Repositories register in `initRepositories()`.
- **Never hand-edit the admin SPA assets** in `view/adminhtml/web/` — they are copied from the `sequra-core-admin-fe` npm package. Bump the package and re-import with `bin/update-integration-core-ui`.

## Common commands

This module is developed *inside* a Dockerized Magento install. `./setup.sh --install` boots Magento with the module mounted, then prints the store URL, admin URL, and admin credentials on completion. Those values are read from `.env` (`M2_URL`, `M2_ADMIN_USER`, `M2_ADMIN_PASSWORD`, `M2_HTTP_PORT`, `M2_HTTP_HOST`) — created from `.env.sample` on first run — so don't assume the defaults; read the setup output or your `.env`. The default host requires `127.0.0.1 localhost.sequrapi.com` in `/etc/hosts`. `./teardown.sh` tears it down. All `bin/*` scripts are wrappers that run their tool in the right container/image.

```bash
bin/magento <args>          # Magento CLI inside the container (e.g. setup:upgrade, cache:flush)
bin/composer <args>         # Composer inside the container
bin/update-sequra           # reinstall this module into Magento's vendor/ from the working tree
bin/update-integration-core-ui   # re-import admin SPA assets after bumping the npm package
```

**Lint & static analysis (these run in CI — match them before pushing):**
```bash
bin/phpcs                   # PHP_CodeSniffer, standard = .phpcs.xml.dist (Magento2 coding standard); add -q for quiet
bin/phpcbf                  # auto-fix PHPCS violations
bin/phpstan                 # PHPStan level 9, config phpstan.neon — REQUIRES the docker env running (docker compose exec magento) and bin/update-sequra to have populated vendor/
```
CI also runs `php -l` syntax linting across PHP 7.4–8.4. `composer.json` requires `php >=7.4 <8.6`.

**Unit/integration tests** (`Test/`, PHPUnit) run against a Magento install — see `Test/README.md`. Tests autoload core's test base classes from `vendor/sequra/integration-core/tests/...` (see `autoload-dev` in `composer.json`). Note: tests fail if the module is installed in Magento *and* present locally (double-load); rename one folder when running.

**E2E tests** (`tests-e2e/`, Playwright):
```bash
bin/playwright              # headless run in the official Playwright Docker image
bin/playwright --ui         # recommended: UI mode
bin/playwright --headed
bin/playwright tests-e2e/specs/example.spec.js   # single spec (path, or bare name)
```
E2E requires the container exposed to the internet for SeQura callbacks — run `./setup.sh --ngrok` (or `--cloudflared`) and set `SQ_MERCHANT_REF`, `SQ_USER_SECRET`, `SQ_ASSETS_KEY` in `.env`. Config: `playwright.config.js` (single `chromium` project covering `tests-e2e/specs/`).

## Conventions

- After changing PHP that touches DI, observers, or schema, run `bin/magento setup:upgrade` and `bin/magento cache:flush` in the running container.
- Versioning: bump `version` in `composer.json` **and** `setup_version` in `etc/module.xml` together (they must match the git tag), and add a `Setup/Patch/Data/Version*.php` patch when data migration is needed.
- `etc/module.xml` declares load-order `sequence` against Magento core modules — keep it in sync when adding dependencies on other Magento modules.
- `bin/xdebug --mode=debug|profile|off` toggles XDebug; debug config and SeQura Helper dev webhooks are documented in `README.md`.
- `phpstan.neon` paths point at `/var/www/html/...` (container paths) and include the dev-only `Sequra/Helper` module from `.docker/`.

## Working style

Repo-specific guardrails. For trivial tasks, use judgment.

- **Confirm which side of an interface you're touching before editing.** The sharp edges here are the `Sequra\Core\` vs `SeQura\Core\` namespaces, the DI virtual types in `etc/di.xml`, and service registration in `Services/Bootstrap.php`.
- **Prefer Magento's declarative wiring** (`di.xml`, `events.xml`, `db_schema.xml`) over bespoke PHP plumbing. A new integration is usually "implement the core `*ServiceInterface`, register it in `Bootstrap`" — not a new framework.
- **Match the existing style.** Code must pass `bin/phpcs` (Magento2 standard) and PHPStan level 9 unchanged; don't reformat adjacent code, and remove only the imports/properties/`use` statements *your* change orphaned.
- **Never hand-edit the copied admin SPA assets** in `view/adminhtml/web/` — bump the `sequra-core-admin-fe` package and re-import instead.
- **Don't refactor what isn't broken.** If you spot unrelated dead code, mention it rather than delete it.

### Goal-driven execution

Define success criteria, then loop until verified. "Add validation" or "fix the bug" → write the PHPUnit test (`Test/`) first, then make it pass. "Refactor X" → `bin/phpcs`, `bin/phpstan` and the tests pass before and after.

For multi-step work, state a brief plan with a verify step each, e.g.:
```
1. Implement FooServiceInterface in Services/BusinessLogic → verify: bin/phpstan clean
2. Register it in Bootstrap::initServices() → verify: bin/magento setup:upgrade + service resolves
3. Add unit test → verify: PHPUnit green
```
Always close the loop with the relevant gate (`bin/phpcs`, `bin/phpstan`, PHPUnit, or `bin/playwright`) — don't declare done on inspection alone.
