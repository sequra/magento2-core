# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

`Sequra_Core` — the Magento 2 module (`composer` type `magento2-module`, PSR-4 root `Sequra\Core\`) that integrates SeQura payment methods into a Magento store. The module itself is a thin adapter: nearly all business logic lives in the `sequra/integration-core` Composer package (PSR-4 `SeQura\Core\` — note the capital `Q`). This repo implements the Magento-specific *integration interfaces* that core defines, and wires them in via dependency injection.

**Two namespaces, easy to confuse:**
- `Sequra\Core\...` (lowercase `q`) → this repository.
- `SeQura\Core\...` (capital `Q`) → the `sequra/integration-core` SDK in `vendor/`, where the domain/business logic lives.

## Architecture

**Bootstrap & service wiring.** `Services/Bootstrap.php` extends core's `BootstrapComponent`. It overrides `initServices()` and `initRepositories()` to register this module's concrete implementations against core's interfaces using `ServiceRegister::registerService(...)` and `RepositoryRegistry::registerRepository(...)`. `Observer/ServiceRegisterObserver.php` triggers `Bootstrap::init()` so the container is populated at runtime. To add a new integration service: implement the core `*ServiceInterface` under `Services/BusinessLogic/`, then register it in `Bootstrap::initServices()`.

**Persistence.** All core entities (`ConnectionData`, `CountryConfiguration`, `GeneralSettings`, `SeQuraOrder`, `PaymentMethod`, `Credentials`, `Deployment`, queue items, etc.) are stored through core's ORM, backed here by `Repository/BaseRepository.php` (generic) and specialized repos like `Repository/SeQuraOrderRepository.php` and `Repository/QueueItemRepository.php`. Schema lives in `etc/db_schema.xml`; data migrations are versioned patches under `Setup/Patch/Data/Version*.php` and `Setup/Patch/Schema/`.

**Payment gateway.** Configured almost entirely declaratively in `etc/di.xml` via Magento's payment-facade virtual types: `SequraPaymentGatewayFacade` (`Magento\Payment\Model\Method\Adapter`), `SequraPaymentGatewayCommandPool` (capture/refund), and the value-handler pool. PHP gateway classes under `Gateway/` (`Request/`, `Http/`, `Response/`) build, transfer, and handle responses for capture/refund/void/order-update against the SeQura API. The payment method code constant is `Sequra\Core\Model\Ui\ConfigProvider::CODE`.

**Checkout payment-methods API.** Frontend checkout fetches available SeQura methods and the payment form through REST endpoints declared in `etc/webapi.xml` (`/V1/sequra_core/...`), served by `Model/Api/` services. There are guest vs. customer variants and a newer `Checkout/` set, wired through `di.xml` virtual types (`Sequra{Customer,Guest}PaymentService`, etc.) with `CartProvider` strategies.

**Controllers.** `Controller/Webhook/` and `Controller/IntegrationWebhook/` receive SeQura callbacks; `Controller/Comeback/` and `Controller/Hpp/` handle the hosted-payment-page return flow; `Controller/AsyncProcess/` runs core's async task queue; `Controller/Adminhtml/Configuration/` backs the admin onboarding/settings UI. CSRF for webhook endpoints is handled by `Plugin/Framework/App/Request/CsrfValidator.php`.

**Admin UI.** The configuration screen is a single-page app shipped as prebuilt assets from the `sequra-core-admin-fe` (a.k.a. `integration-core-ui`) npm package, copied into `view/adminhtml/web/`. Do **not** hand-edit those copied assets — update the package version and re-import (see below).

**Order lifecycle.** `Observer/` hooks Magento order events (cancellation, shipment, address changes) to keep SeQura order state in sync; `Plugin/OrderDetails.php` and the widget plugins augment storefront/admin rendering. Promotional widgets and banners are configured through `Block/Widget*`, `Block/Banner.php`, and `Services/BusinessLogic/PromotionalWidget/`.

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

Behavioral guidelines to reduce common mistakes. These bias toward caution over speed — for trivial tasks, use judgment.

### 1. Think before coding

Don't assume. Don't hide confusion. Surface tradeoffs.

- State assumptions explicitly; if uncertain, ask. This codebase has sharp edges — the two `Sequra\Core\` vs `SeQura\Core\` namespaces, DI virtual types in `etc/di.xml`, and service registration in `Services/Bootstrap.php`. Confirm which side of an interface you're touching before editing.
- If multiple interpretations exist, present them — don't pick silently.
- If a simpler approach exists, say so. Push back when warranted.
- If something is unclear, stop, name what's confusing, and ask.

### 2. Simplicity first

Minimum code that solves the problem. Nothing speculative.

- No features, abstractions, or configurability beyond what was asked.
- Prefer Magento's declarative wiring (`di.xml`, `events.xml`, `db_schema.xml`) over bespoke PHP plumbing. A new integration is usually "implement the core `*ServiceInterface`, register it in `Bootstrap`" — not a new framework.
- No error handling for impossible scenarios.
- If you write 200 lines and it could be 50, rewrite it. Would a senior Magento engineer call this overcomplicated? If yes, simplify.

### 3. Surgical changes

Touch only what you must. Clean up only your own mess.

- Match the existing style — code must pass `bin/phpcs` (Magento2 standard) and PHPStan level 9 unchanged. Don't reformat adjacent code.
- Never hand-edit the copied admin SPA assets in `view/adminhtml/web/` — they come from the `sequra-core-admin-fe` npm package; bump the package and re-import instead.
- Don't refactor things that aren't broken; if you spot unrelated dead code, mention it rather than delete it.
- Remove only imports/properties/`use` statements that *your* change orphaned.
- The test: every changed line should trace directly to the request.

### 4. Goal-driven execution

Define success criteria. Loop until verified.

- "Add validation" → write a PHPUnit test (`Test/`) for the invalid input, then make it pass.
- "Fix the bug" → write a test that reproduces it, then make it pass.
- "Refactor X" → ensure `bin/phpcs`, `bin/phpstan`, and the tests pass before and after.

For multi-step work, state a brief plan with a verify step each, e.g.:
```
1. Implement FooServiceInterface in Services/BusinessLogic → verify: bin/phpstan clean
2. Register it in Bootstrap::initServices() → verify: bin/magento setup:upgrade + service resolves
3. Add unit test → verify: PHPUnit green
```
Always close the loop with the relevant gate (`bin/phpcs`, `bin/phpstan`, PHPUnit, or `bin/playwright`) — don't declare done on inspection alone.
