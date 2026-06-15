---
name: version-bump
description: Bump the Sequra_Core module version safely across all the places that must stay in lockstep — composer.json "version", etc/module.xml setup_version, and (only when a data migration is needed) a new Setup/Patch/Data/Version*.php patch — plus a reminder that the git tag must match. Use when releasing or preparing a new module version (e.g. "bump to 3.3.1", "prepare the 3.4.0 release").
---

# Bump the Sequra_Core module version

The version lives in **three** coupled places and a desync has shipped before
(commit `12f117c` — *"fix: bump version to 3.2.2 to match git tag"*). This skill
changes them together so nothing drifts.

The three coupled values:
1. `composer.json` → `"version": "X.Y.Z"`
2. `etc/module.xml` → `<module name="Sequra_Core" setup_version="X.Y.Z">`
3. The **git tag** `X.Y.Z` (created at release time — this skill does NOT tag; it
   only ensures the files will match the tag you cut).

A `Setup/Patch/Data/Version*.php` patch is added **only when the bump needs a data
migration** — most bumps don't (e.g. `3.2.2` shipped without one).

## Before you start

1. Get the target version `X.Y.Z` (semver). Confirm it's strictly greater than the
   current value in `composer.json` / `etc/module.xml`.
2. Read the current values so you know what you're replacing:
   - `grep '"version"' composer.json`
   - `grep setup_version etc/module.xml`
3. Decide: **does this release need a data migration** (schema already lives in
   `etc/db_schema.xml`; this is for *data* changes / one-off tasks run on upgrade)?
   - **No** → skip step 3 below. This is the common case.
   - **Yes** → scaffold the patch in step 3.

## Steps

### 1. `composer.json`
Replace the `version` value. It's a top-level key, near the top of the file:
```diff
-    "version": "3.2.2",
+    "version": "X.Y.Z",
```

### 2. `etc/module.xml`
Replace `setup_version` on the `<module name="Sequra_Core" ...>` element:
```diff
-    <module name="Sequra_Core" setup_version="3.2.2">
+    <module name="Sequra_Core" setup_version="X.Y.Z">
```
Leave the `<sequence>` block untouched (that only changes when module dependencies do).

### 3. Data patch — ONLY if a data migration is needed
Create `Setup/Patch/Data/Version<PACKED>.php`, where **`<PACKED>` is the version with
dots removed** (`3.3.0` → `Version330`, `3.2.1` → `Version321`). Mirror the existing
patches exactly (see `Setup/Patch/Data/Version330.php`):

```php
<?php

namespace Sequra\Core\Setup\Patch\Data;

use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use SeQura\Core\Infrastructure\Logger\Logger;
use Throwable;

class Version<PACKED> implements DataPatchInterface
{
    /**
     * @var ModuleDataSetupInterface
     */
    private ModuleDataSetupInterface $moduleDataSetup;

    /**
     * @param ModuleDataSetupInterface $moduleDataSetup
     */
    public function __construct(
        ModuleDataSetupInterface $moduleDataSetup
    ) {
        $this->moduleDataSetup = $moduleDataSetup;
    }

    /**
     * @inheritDoc
     */
    public static function getDependencies(): array
    {
        return [];
    }

    /**
     * @inheritDoc
     */
    public function getAliases(): array
    {
        return [];
    }

    /**
     * @inheritDoc
     */
    public function apply(): void
    {
        $this->moduleDataSetup->startSetup();

        try {
            // TODO: the actual migration. Existing patches run a core task, e.g.
            // (new StoreIntegrationMigrateTask())->execute();
            // Import the task from SeQura\Core\BusinessLogic\Domain\... and add its
            // `use` statement above. Remove this block if there's truly nothing to do
            // (in which case you probably don't need a patch at all).

            Logger::logInfo('Migration ' . self::class . ' has been successfully finished.');
        } catch (Throwable $e) {
            // NOTE: the log string uses the DOTTED version, not the packed class suffix.
            Logger::logError('Update script VX.Y.Z execution failed because: ' . $e->getMessage());
        }

        $this->moduleDataSetup->endSetup();
    }
}
```
Conventions to honor:
- Namespace is **`Sequra\Core\...`** (lowercase `q` — this repo). Imported tasks live
  under **`SeQura\Core\...`** (capital `Q` — the SDK). Don't mix them up.
- `getDependencies()` / `getAliases()` return `[]` like the existing patches unless you
  have a real ordering requirement.
- The `logError` string uses the dotted `VX.Y.Z`, matching `Version321`/`Version330`.

> Caveat on `<PACKED>`: stripping dots is lossy for multi-digit segments
> (`3.10.0` → `3100`). It matches the existing convention, but if a segment ever goes
> double-digit, flag the ambiguity rather than silently colliding.

## Verify (close the loop)

1. **The two anchors match each other and the target** — both must print `X.Y.Z`:
   ```bash
   grep '"version"' composer.json
   grep setup_version etc/module.xml
   ```
2. **If you added a patch:** apply it in the running container and confirm it runs clean:
   ```bash
   bin/magento setup:upgrade
   bin/magento cache:flush
   ```
   (Requires the Docker env up — see CLAUDE.md. The pre-push hook's phpstan also needs it.)
3. **Lint stays green** (the new patch file must pass): `bin/phpcs` and, with the env up,
   `bin/phpstan`.
4. **Remind the user about the git tag.** The release tag must be `X.Y.Z` so it matches
   the files — this is what `12f117c` had to fix. State it; do NOT create or push the tag
   unless explicitly asked (tagging/releasing is an outward action).

## Output discipline

- Touch only the version anchors (+ the one patch file when needed). Don't bump unrelated
  deps, reformat the files, or edit the `<sequence>` block.
- Most bumps are just steps 1–2. Don't scaffold a patch "just in case" — an empty patch
  that does nothing is noise.
- After changing, list exactly which files you touched and print the two `grep` results so
  the lockstep is visible.
