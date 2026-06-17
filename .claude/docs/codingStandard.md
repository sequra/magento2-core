# Coding Standard

Conventions for **`Sequra_Core`** — the Magento 2 module that bridges Magento's
payment framework with the platform-agnostic `sequra/integration-core` library.
Unlike the core library, this is **Magento-specific code**: it uses Magento DI,
Blocks/Controllers/Plugins/Observers/Gateway, and consumes integration-core
through its facades and `Domain/Integration` interfaces.

> This is the authoritative standard for **this module**. The integration-core
> standard (`integration-core/.claude/docs/codingStandard.md`) governs the
> library; where the two differ (PHP floor, PHPStan level, DI mechanism, allowed
> language features) **this file wins for `Sequra_Core`**.

## 0. The quality gate (non-negotiable)

CI (`.github/workflows/static-analysis.yml`) runs, in this order — and a PR is
not done until all pass:

1. **PHP syntax** on **7.4, 8.0, 8.1, 8.2, 8.3, 8.4, 8.5** (`php -l`).
2. **PHPCS** — `./bin/phpcs -q` (Magento2 coding standard + PHPCompatibility, `.phpcs.xml.dist`).
3. **PHPStan** — `./bin/update-sequra` then `./bin/phpstan` (**level 9**, `phpstan.neon`).

So the rule is: **`phpcs` → `phpstan` must both pass** (after the syntax sweep).
Fix style first (`./bin/phpcbf` auto-fixes most), then resolve every phpstan
finding — **don't silence the analyser**; the only sanctioned ignores are for
Magento's auto-generated factories (see §5).

**All dev tools run inside Docker via the `bin/` wrappers — never run
phpcs/phpstan/composer directly on the host.** Common commands:

```bash
./bin/phpcs -q            # check style
./bin/phpcbf              # auto-fix style
./bin/update-sequra       # reinstall the module into Magento's vendor (do before phpstan)
./bin/phpstan             # static analysis, level 9
```

## 1. Architecture

`Sequra_Core` is a standard Magento 2 module. Source lives under PSR-4 root
`Sequra\Core\` (maps to the repo root). Layers/areas:

| Area | Location | Notes |
|------|----------|-------|
| Payment gateway components | `Gateway/` | Magento Payment Gateway framework (capture/refund/void) |
| Storefront/admin controllers | `Controller/` | `Adminhtml/` (custom admin UI), `ExpressCheckout/`, webhook/comeback |
| REST endpoints | `etc/webapi.xml` + `Api/` (interfaces) + `Model/Api/` (impl) | |
| Blocks / view models | `Block/` | + `view/frontend|adminhtml/templates/*.phtml` |
| Plugins (interceptors) | `Plugin/` | e.g. `CsrfValidator`, `MiniWidgets` |
| Observers | `Observer/` (wired in `etc/events.xml`) | |
| Models / persistence | `Model/`, `etc/db_schema.xml` | tables `sequra_entity`, `sequra_queue`, `sequra_order` |
| DI wiring | `etc/di.xml`, `etc/frontend/di.xml` | virtual types, preferences, plugins |
| **integration-core bridge** | `Services/Bootstrap.php` | registers ~20 core service implementations |

## 2. Consuming integration-core (don't fork it)

This module is a **consumer** of `sequra/integration-core`. Never edit the
library from here.

- **Call business logic through the core facades** — `CheckoutAPI::get()->…`,
  `AdminAPI::get()->…` — which return a `Response` (`isSuccessful()` + `toArray()`).
  Treat an unsuccessful response as the failure path; don't expect exceptions.
- **Provide platform capabilities by implementing `Domain/Integration/*Interface`**
  in `Services/` (e.g. product/category/order/store services) and **registering
  them in `Services/Bootstrap.php`** (which extends the core `BootstrapComponent`).
  When the core needs new shop data, the contract is added there — not invented here.
- Keep Magento types out of arguments passed into the core; map to the core's
  request DTOs at the boundary (controllers / `Model/Api/`).

## 3. PHP language floor (7.4)

The module targets **PHP 7.4 → 8.5** (`composer.json: ">=7.4 <8.6"`, CI syntax
sweep 7.4–8.5). Code must parse on **7.4** and on **8.5**:

- ✅ **Typed properties** (`private RawFactory $resultRawFactory;`), arrow
  functions, `??=`, array spread — all 7.4, use them.
- ✅ Nullable/scalar type hints, `void`/`self` returns.
- ❌ **8.0+ features are off-limits**: constructor property promotion, union
  types, named arguments, `match`, nullsafe `?->`, attributes; ❌ **8.1+**:
  `enum`, `readonly`, first-class callable syntax, `never`.
- Express closed sets as class constants or small value objects, not `enum`.

(This is the key divergence from integration-core, whose floor is 7.2 and which
therefore forbids typed properties — here they are expected.)

## 4. Dependency injection (Magento)

- **Constructor injection only**, resolved by Magento's ObjectManager from
  `etc/di.xml`. Don't call `ObjectManager` directly; don't `new` services.
- Depend on **interfaces / `…Interface`** where one exists; use **virtual types**
  and `<preference>` in `di.xml` for configuration (e.g. the gateway facade).
- **Factories**: Magento auto-generates `<Class>Factory`. Inject and use them
  (`$this->fooFactory->create()`) for non-shared/data objects. These generated
  classes are invisible to PHPStan — see §5.
- A new injected dependency only needs the constructor param; framework-built
  classes (Blocks, Controllers) need no manual wiring.

## 5. PHPStan (level 9) — the Magento gotchas

Level 9 is strict about `mixed`. The recurring patterns in this codebase:

- **Auto-generated factories → ignore in `phpstan.neon`.** PHPStan can't see
  generated `*Factory` classes, so add the class to the `ignoreErrors` block
  (the established convention — `OrderFactory`, `RawFactory`, `QuoteFactory`,
  `CreateOrderRequestBuilderFactory`, …). Add **both** message shapes when hit:
  `#unknown class …\\FooFactory#i` and `#invalid type …\\FooFactory#i`. This is
  the **only** sanctioned use of `ignoreErrors`.
- **Magento getters return `mixed` — never blind-cast.** `$model->getId()`,
  `getTypeId()`, `DeploymentConfig::get()`, request `getParam()` are `mixed`
  (or `array|string`). Casting `(string)$mixed` fails level 9 — **narrow first**:
  `is_scalar($x) ? (string)$x : ''` / `is_string($x) ? $x : ''`.
- **Magic setters/getters are "undefined" to PHPStan.** `$quote->setTotalsCollectedFlag(false)`
  isn't a real method → use the real `$quote->setData('totals_collected_flag', false)`
  (and `getData(...)` to read). Prefer the concrete-method API when one genuinely exists.
- **Respect interface arity.** A `Data` interface may declare fewer params than
  the model (`OrderPaymentInterface::getAdditionalInformation()` takes 0 args) —
  call it as the interface declares and index the returned array.
- Prefer a **structural fix** over an ignore: e.g. pass a typed `array` instead of
  re-parsing a query string, or use `getParams(): array` instead of `getQuery()` (`mixed`).

## 6. PHPCS (Magento2 standard) — the recurring rules

- **Docblock short description must be a single line** (Magento2.Annotation):
  one-line summary, blank `*` line, then the long description.
- **Every parameter needs an `@param`**, and the type must parse — the generic
  `array<string, mixed>` form can trip the sniff; use `mixed[]` (or `Type[]`).
- **Public methods need full docblocks** (`@param`/`@return`/`@throws`).
- **Line length ≤ 120**; extract a variable rather than wrapping a PHP expression
  inside a `.phtml` attribute.
- **Discouraged functions** (e.g. `parse_str`, `extract`): avoid them — refactor
  so they're unnecessary; only as a last resort add a justified, single-line
  `// phpcs:ignore Magento2.Functions.DiscouragedFunction.Discouraged` directly
  above the call.
- Run `./bin/phpcbf` to auto-fix spacing/indent/EOL before hand-fixing.

## 7. Type safety & docblocks

- Type hints on every param/return that 7.4 allows; document array shapes
  (`@param mixed[] $x`, `@return array<string, mixed>`) — never a bare `array`.
- `@throws` lists every propagated exception.
- A class docblock (1–3 lines) on every class; `@inheritDoc` is fine on overrides.

## 8. Storefront controllers, blocks & templates

- **Controllers** implement the specific action interface
  (`HttpGetActionInterface` / `HttpPostActionInterface`) — never both; this
  restricts the HTTP method.
- Returning raw HTML/JSON uses `RawFactory`/`ResultFactory`; set explicit
  `Cache-Control: no-store` on per-session/stateful responses so they're never
  full-page cached.
- **CSRF**: storefront POST controllers are CSRF-validated by default;
  exemptions live in `Plugin/Framework/App/Request/CsrfValidator.php` (kept as
  narrow as possible).
- **Templates (`.phtml`)**: always escape output via `$escaper->escapeHtml*()` /
  `$block->escapeHtml()` — unescaped output trips `Magento2.Security.XssTemplate`.
  Keep logic out of templates; compute in the Block/ViewModel.

## 9. General style

- KISS / DRY / YAGNI; one class per file; `Interface` suffix, `Abstract` prefix.
- Verb-noun methods (`getSolicitUrl`, `solicit`); booleans `is/has/should/can`.
- Class constants for magic strings/numbers; no globals.
- **Stage new source files immediately** — when you add a PHP/JS/XML source file,
  `git add` it the same change so it isn't left untracked (ad-hoc scratch `*.md`
  in the repo root are exempt).
- Match the surrounding code's formatting; `./bin/phpcbf` settles disputes.

## 10. Checklist for a new class / feature

- [ ] Placed in the correct module area (§1); platform capabilities go through a
      `Domain/Integration` interface implemented in `Services/`, not ad-hoc calls (§2).
- [ ] PHP **7.4**-safe syntax (§3); typed properties OK, no 8.0+ syntax.
- [ ] DI via constructor + `di.xml`; new generated factories added to `phpstan.neon` ignores (§5).
- [ ] `mixed`/magic-method access narrowed or routed through `setData/getData` (§5).
- [ ] Full docblocks, single-line short description, `mixed[]` array types (§6).
- [ ] `.phtml` output escaped (§8); new source files `git add`-ed (§9).
- [ ] Green end-to-end: **`./bin/phpcs -q` → `./bin/update-sequra` → `./bin/phpstan`**, all on PHP 7.4–8.5.
