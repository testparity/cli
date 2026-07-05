# S005 Interface Requirements

### PluginLoader Public API

S005-IF-001 [P1] The `PluginLoader` class **MUST** be defined in namespace `App\Services` and expose the following public methods:

```php
class PluginLoader
{
    public function loadAll(RuleRegistry $registry, string $projectRoot): void;
    public function loadDirectory(RuleRegistry $registry, string $directory): void;
    public function loadFile(RuleRegistry $registry, string $file): void;
    public function loadComposerPlugins(RuleRegistry $registry, string $projectRoot): void;
    public function getWarnings(): array;
}
```

S005-IF-001.a `loadAll()` **MUST** be the primary entry point, invoking all three discovery mechanisms in order (project-local, global-user, Composer).
S005-IF-001.b `loadDirectory()` **MUST** be callable independently for loading plugins from any arbitrary directory.
S005-IF-001.c `loadFile()` **MUST** be callable independently for loading a single plugin file.
S005-IF-001.d `loadComposerPlugins()` **MUST** be callable independently for loading only Composer-declared plugins.
S005-IF-001.e `getWarnings()` **MUST** return `list<string>` -- an indexed array of warning messages accumulated during loading.
S005-IF-001.f The `PluginLoader` **MUST** store warnings as instance state. Calling `loadAll()` or individual load methods multiple times **MUST** accumulate warnings (not reset them).

### Plugin File Return Type Contract

S005-IF-002 [P1] A plugin PHP file loaded from a directory **MUST** adhere to the following return contract:

```php
<?php
// Option A: Return a single rule instance
return new MyCustomRule();

// Option B: Return an array of rule instances
return [
    new MyFirstRule(),
    new MySecondRule(),
];
```

S005-IF-002.a The file **MUST** be a valid PHP file that can be loaded via `require`.
S005-IF-002.b The file's return value **MUST** be either a `RuleInterface` instance or an array of values (where `RuleInterface` instances are extracted and non-conforming elements are silently ignored).
S005-IF-002.c The file **MAY** contain class definitions, function definitions, or any other valid PHP code, but only the return value is processed by the loader.
S005-IF-002.d The file **MUST NOT** rely on any specific variables being in scope; it is loaded via bare `require` with only `$registry` and `$file` in the calling scope.

### Composer extra.parity.rules Schema

S005-IF-003 [P2] A Composer package distributing Parity rules **MUST** declare them in its `composer.json` as follows:

```json
{
    "name": "vendor/parity-rule-package",
    "extra": {
        "parity": {
            "rules": [
                "Vendor\\ParityRules\\MyCustomRule",
                "Vendor\\ParityRules\\AnotherRule"
            ]
        }
    },
    "autoload": {
        "psr-4": {
            "Vendor\\ParityRules\\": "src/"
        }
    }
}
```

S005-IF-003.a The `extra.parity.rules` value **MUST** be an array of strings, where each string is a fully qualified class name (FQCN).
S005-IF-003.b Each declared class **MUST** implement `RuleInterface` (S002-IF-001).
S005-IF-003.c Each declared class **MUST** have a no-argument constructor (or a constructor where all parameters have defaults).
S005-IF-003.d The package **MUST** configure PSR-4 autoloading so that its classes are loadable after `composer install`.
S005-IF-003.e The `extra.parity` key **MAY** contain additional keys in the future; the loader **MUST** only read `rules`.

### Plugin Authoring Minimal Example

S005-IF-004 [P2] A minimal valid plugin file **MUST** conform to the following structure:

```php
<?php
// .parity/plugins/MyRule.php

use App\Rules\RuleInterface;
use App\Rules\RuleContext;
use App\Rules\RuleResult;

return new class implements RuleInterface
{
    public function name(): string
    {
        return 'my-custom-rule';
    }

    public function parameters(): array
    {
        return [];
    }

    public function evaluate(RuleContext $context, array $params): RuleResult
    {
        // Custom logic here
        return RuleResult::pass();
    }

    public function columnHeader(): ?string
    {
        return 'Custom';
    }

    public function formatCell(RuleResult $result): string
    {
        return $result->passed ? 'OK' : 'FAIL';
    }

    public function isEnforced(): bool
    {
        return true;
    }
};
```

S005-IF-004.a Anonymous classes (via `new class implements RuleInterface`) **MUST** be supported as a valid plugin authoring pattern.
S005-IF-004.b Named classes defined and instantiated within the same file **MUST** also be supported.
S005-IF-004.c The plugin file's `use` statements **MUST** reference the `App\Rules` namespace for `RuleInterface`, `RuleContext`, and `RuleResult`.

### Plugin Rule Configuration in parity.yaml

S005-IF-005 [P1] Plugin rules **MUST** be referenceable in `parity.yaml` by their `name()` return value, using the same syntax as built-in rules (per S002-IF-005):

```yaml
rules:
  # String format (no params)
  - my-custom-rule

  # Map format (with params)
  - my-custom-rule:
      some_param: value
```

S005-IF-005.a The configuration system **MUST NOT** distinguish between plugin rules and built-in rules in `parity.yaml`.
S005-IF-005.b If a plugin rule name referenced in `parity.yaml` is not registered (plugin failed to load or was not installed), the `RuleRegistry::resolve()` **MUST** throw `InvalidArgumentException` per S002-FR-005.e.
