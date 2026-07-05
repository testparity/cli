# S005 Acceptance Scenarios

### S005-AS-001 Project-local plugin discovered and registered [P1]

**Given** a project at `/path/to/project` with a file `.parity/plugins/CustomRule.php`
**And** that file returns an object implementing `RuleInterface` with `name()` returning `"custom-rule"`
**When** `PluginLoader::loadAll()` is called with that project root
**Then** `RuleRegistry::has('custom-rule')` **MUST** return `true`
**And** `RuleRegistry::get('custom-rule')` **MUST** return the instance from the file

### S005-AS-002 Global-user plugin discovered and registered [P2]

**Given** the `HOME` environment variable is `/home/user`
**And** a file `/home/user/.parity/plugins/GlobalRule.php` exists
**And** that file returns an object implementing `RuleInterface` with `name()` returning `"global-rule"`
**When** `PluginLoader::loadAll()` is called
**Then** `RuleRegistry::has('global-rule')` **MUST** return `true`

### S005-AS-003 Composer plugin discovered and registered [P2]

**Given** a project at `/path/to/project` with `vendor/composer/installed.json` containing:
```json
{
    "packages": [{
        "name": "vendor/parity-extra-rules",
        "extra": { "parity": { "rules": ["Vendor\\ExtraRules\\StrictNaming"] } }
    }]
}
```
**And** `vendor/autoload.php` exists and loads `Vendor\ExtraRules\StrictNaming`
**And** `StrictNaming` implements `RuleInterface` with `name()` returning `"strict-naming"`
**When** `PluginLoader::loadAll()` is called with that project root
**Then** `RuleRegistry::has('strict-naming')` **MUST** return `true`

### S005-AS-004 Plugin returning array registers multiple rules [P1]

**Given** a plugin file `.parity/plugins/MultiRule.php` that returns:
```php
return [new RuleA(), new RuleB()];
```
**And** `RuleA` implements `RuleInterface` with `name()` returning `"rule-a"`
**And** `RuleB` implements `RuleInterface` with `name()` returning `"rule-b"`
**When** the file is loaded
**Then** `RuleRegistry::has('rule-a')` **MUST** return `true`
**And** `RuleRegistry::has('rule-b')` **MUST** return `true`

### S005-AS-005 Invalid plugin produces warning, not fatal error [P1]

**Given** a plugin file `.parity/plugins/Broken.php` that throws a `RuntimeException` with message `"oops"`
**When** `PluginLoader::loadAll()` is called
**Then** Parity **MUST NOT** terminate with an unhandled exception
**And** `getWarnings()` **MUST** contain a string matching `"Plugin .*/Broken.php failed to load: oops"`
**And** all other plugins in the same and subsequent directories **MUST** still be loaded

### S005-AS-006 Name collision resolved by last-write-wins [P1]

**Given** a built-in rule `"test-exists"` is registered in the `RuleRegistry`
**And** a project-local plugin file returns a rule with `name()` returning `"test-exists"`
**When** `PluginLoader::loadAll()` is called
**Then** `RuleRegistry::get('test-exists')` **MUST** return the plugin's instance (not the built-in)
**And** `getWarnings()` **MUST NOT** contain any warning about the collision

### S005-AS-007 Plugin rule evaluated like built-in rule [P1]

**Given** a custom plugin rule `"no-abstract-without-test"` is registered via project-local plugin
**And** `parity.yaml` includes `no-abstract-without-test` in a structure's `rules` array
**When** the `check` command evaluates source files
**Then** the plugin rule **MUST** receive the same `RuleContext` as built-in rules
**And** if the plugin rule's `isEnforced()` returns `true` and `evaluate()` returns a failing `RuleResult`, the check **MUST** fail

### S005-AS-008 Plugin rule appears in table output [P1]

**Given** a custom plugin rule is registered with `columnHeader()` returning `"Custom"`
**And** the rule is listed in the structure's `rules` array
**When** the `check` command runs in table output mode
**Then** a column with header `"Custom"` **MUST** appear in the output table
**And** the column's cell values **MUST** be the return values of the plugin rule's `formatCell()` method

### S005-AS-009 Missing plugin directory silently skipped [P2]

**Given** the directory `.parity/plugins/` does not exist in the project root
**And** the directory `~/.parity/plugins/` does not exist
**When** `PluginLoader::loadAll()` is called
**Then** no errors or warnings **MUST** be produced for the missing directories
**And** Composer plugin discovery **MUST** still proceed

### S005-AS-010 Plugin with throwing constructor produces warning [P2]

**Given** a Composer package declares `extra.parity.rules: ["Vendor\\Rules\\BrokenRule"]`
**And** `BrokenRule` exists and implements `RuleInterface` but its constructor throws a `\RuntimeException`
**When** `PluginLoader::loadComposerPlugins()` is called
**Then** `getWarnings()` **MUST** contain a string identifying the package name, class name, and exception message
**And** processing of other packages **MUST** continue

### S005-AS-011 Composer plugin with missing class produces warning [P2]

**Given** a Composer package `vendor/parity-rules` declares `extra.parity.rules: ["Vendor\\Rules\\NonExistent"]`
**And** `class_exists('Vendor\\Rules\\NonExistent')` returns `false`
**When** `PluginLoader::loadComposerPlugins()` is called
**Then** `getWarnings()` **MUST** contain a string matching `"Composer plugin vendor/parity-rules: class Vendor\\Rules\\NonExistent not found"`
