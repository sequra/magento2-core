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

# One worktree per write-capable session, each with its own ports and shop (see Git)
bin/workspace <name> [--branch <branch>]
bin/workspace list
bin/workspace rm <name>
```

**Lint & static analysis (these run in CI — match them before pushing):**
```bash
bin/phpcs                   # PHP_CodeSniffer, standard = .phpcs.xml.dist (Magento2 coding standard); add -q for quiet
bin/phpcbf                  # auto-fix PHPCS violations
bin/phpstan                 # PHPStan level 9, config phpstan.neon — REQUIRES the docker env running (docker compose exec magento) and bin/update-sequra to have populated vendor/
```
CI also runs `php -l` syntax linting across PHP 7.4–8.4. `composer.json` requires `php >=7.4 <8.6`.

**Unit tests** (`Test/Unit`, PHPUnit) run against the Magento install in the container, on the `vendor/sequra/magento2-core` copy — so `bin/update-sequra` first, as for phpstan. CI runs them in `.github/workflows/unit-tests.yml`.
```bash
bin/phpunit                 # the Test/Unit suite; extra args go to phpunit (e.g. --filter WebhookAuthenticatorTest)
```
Two tests extend core's test base classes from `vendor/sequra/integration-core/tests/`. The dist package ships without `tests/`, so `bin/phpunit` reinstalls integration-core from source when they are missing, and `Test/Unit/bootstrap.php` maps `SeQura\Core\Tests\` onto it (the module's own `autoload-dev` is ignored when Magento installs it as a dependency). See `Test/README.md` for the integration suite.

**E2E tests** (`tests-e2e/`, Playwright):
```bash
bin/playwright              # headless run in the official Playwright Docker image
bin/playwright --ui         # recommended: UI mode
bin/playwright --headed
bin/playwright tests-e2e/specs/example.spec.js   # single spec (path, or bare name)
```
E2E requires the container exposed to the internet for SeQura callbacks — run `./setup.sh --ngrok` (or `--cloudflared`) and set `SQ_MERCHANT_REF`, `SQ_USER_SECRET`, `SQ_ASSETS_KEY` in `.env`. Config: `playwright.config.js` (single `chromium` project covering `tests-e2e/specs/`).

## Git

### One worktree per write-capable session

Never run two write-capable sessions in the same checkout. A working tree has one HEAD and one index, so a second session's `git checkout` silently relocates the first session's next commit. Read-only sessions may share the primary checkout, but the moment work turns to writing the session moves into a worktree of its own **before** the first write.

Create it with `bin/workspace`, never a bare `git worktree add`: a bare worktree has no `.env`, and a copied one carries the primary's eight host ports (`M2_HTTP_PORT`, `MARIADB_PORT`, `REDIS_PORT`, `OPENSEARCH_API_PORT`, `OPENSEARCH_INTERNODE_PORT`, `ELASTICSEARCH_API_PORT`, `ELASTICSEARCH_INTERNODE_PORT`, `NGROK_PORT`), its tunnel container names (`NGROK_CONTAINER_NAME`, `CLOUDFLARED_CONTAINER_NAME`), which are global to the Docker daemon and which `teardown.sh` removes by name, and its named Cloudflare tunnel, whose hostname routes to one fixed port. `bin/workspace` adds the worktree under `../magento2-core-workspaces/` and writes a `.env` from the primary's (or `.env.sample`) with a port slot of its own (+1000 per workspace: the eight base ports differ in their last three digits, so no two slots ever collide), its own `COMPOSE_PROJECT_NAME`, tunnel names and `M2_URL`, and no named tunnel.

```bash
bin/workspace lis-123-short-title --branch LIS-123-short-title
cd ../magento2-core-workspaces/lis-123-short-title && claude
./setup.sh                              # inside the workspace: its own Magento on its own port
bin/workspace list                      # workspaces, branches, ports, shop running or not
bin/workspace rm lis-123-short-title    # stops its shop, drops its volumes, removes the worktree; keeps the branch
```

- **Workspace names take no slashes** (`^[a-zA-Z0-9_-]+$`), so a branch name containing '/' goes through `--branch`. Without it the branch is named after the workspace.
- **A branch the script has to create starts from `origin/master`**, never from the primary checkout's HEAD; an existing local branch is checked out as-is, and one that exists only on `origin` is tracked. Never move the primary onto master to get a branch - that is the hazard this rule exists to prevent.
- **The workspace builds what is gitignored itself**: `./setup.sh` installs Magento with the module into the workspace's own volumes (`--install` also imports the admin UI), and `npm ci` is needed before `bin/playwright`. No composer step runs at creation: `bin/composer` is `docker compose run`, which would start the whole stack.
- **Only `bin/phpcs` and the syntax sweep run without a shop.** `bin/phpstan`, `bin/phpunit` and `bin/update-sequra` exec into the workspace's own `magento` container, so they need that workspace's `./setup.sh` to have run.
- **`./setup.sh --cloudflared` does not work in a workspace**: the named tunnel is the primary's. `--ngrok` does, on the workspace's own container and port.
- **Never let the harness create the worktree.** `EnterWorktree name=` and subagent `isolation: worktree` nest it under `.claude/worktrees/`, which this repository does not ignore: the tree then sits inside the primary checkout, shows up as untracked in its `git status`, and starts without a `.env`. The supported entry is `cd <worktree> && claude`.

### Always branch, then open a PR

No direct commits to `master`. Branch and commit through the `sq-git:commit` skill and open the PR through `sq-git:open-pr`.

### Assert HEAD in the same breath as the commit

A branch read earlier in the session is stale by the time you commit: another session, a rebase or the operator can move it in between. Check immediately before committing, not once at the start:

```bash
git symbolic-ref --short HEAD    # must be the branch you believe you are on
```

Push the branch you are actually on, `git push -u origin HEAD`. Never `git push origin <hardcoded-branch-name>`: it pushes the local ref of that name wherever HEAD points, and reports "Everything up-to-date" when that ref has not moved, so a misplaced commit fails silently.

## Conventions

- After changing PHP that touches DI, observers, or schema, run `bin/magento setup:upgrade` and `bin/magento cache:flush` in the running container.
- Versioning: bump `version` in `composer.json` **and** `setup_version` in `etc/module.xml` together (they must match the git tag), and add a `Setup/Patch/Data/Version*.php` patch when data migration is needed.
- `etc/module.xml` declares load-order `sequence` against Magento core modules — keep it in sync when adding dependencies on other Magento modules.
- `bin/xdebug --mode=debug|profile|off` toggles XDebug; debug config and SeQura Helper dev webhooks are documented in `README.md`.
- `phpstan.neon` paths point at `/var/www/html/...` (container paths) and include the dev-only `Sequra/Helper` module from `.docker/`.
- **`set -e` does not reach a function or subshell called as `x || die ...`** — bash switches errexit off for everything run on the left of `||`/`&&` or in an `if` condition, so a failing command inside it is skipped and the block's status is its *last* command's. A command whose failure must stop such a block (as `docker compose down` in `bin/workspace rm`) carries its own `|| return 1`; `DOCKER_HOST=unix:///nonexistent.sock` makes docker fail for a real test of that path.

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
