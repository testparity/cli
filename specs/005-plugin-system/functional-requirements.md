# S005 Functional Requirements

### Three-Location Plugin Discovery

S005-FR-001 [P1] The `PluginLoader` **MUST** discover and load plugins from exactly three locations: project-local, global-user, and Composer packages.
S005-FR-001.a The project-local directory **MUST** be `{projectRoot}/.parity/plugins/`.
S005-FR-001.b The global-user directory **MUST** be `{HOME}/.parity/plugins/`, where `{HOME}` is resolved from `$_SERVER['HOME']`, `$_ENV['HOME']`, or `getenv('HOME')` (checked in that order, first non-empty value wins).
S005-FR-001.c Composer packages **MUST** be discovered via the `extra.parity.rules` key in each installed package's metadata within `{projectRoot}/vendor/composer/installed.json`.

### Discovery Order and Precedence

S005-FR-002 [P1] The three discovery locations **MUST** be loaded in the following fixed order: (1) project-local, (2) global-user, (3) Composer packages.
S005-FR-002.a This order, combined with `RuleRegistry`'s last-write-wins behavior (S002-FR-004.e), means Composer packages override global-user plugins, global-user plugins override project-local plugins, and all plugins override built-in rules registered before plugin loading.
S005-FR-002.b The loading order **MUST NOT** be configurable by the user.

### Project-Local Plugin Loading

S005-FR-003 [P1] The loader **MUST** scan the project-local directory (`{projectRoot}/.parity/plugins/`) for files matching the glob pattern `*.php`.
S005-FR-003.a If the directory does not exist, the loader **MUST** silently skip this location without producing a warning or error.
S005-FR-003.b Files **MUST** be loaded in alphabetical (lexicographic sort) order.
S005-FR-003.c Each file **MUST** be loaded via PHP's `require` statement and its return value processed per S005-FR-006.

### Global-User Plugin Loading

S005-FR-004 [P2] The loader **MUST** scan the global-user directory (`{HOME}/.parity/plugins/`) for files matching the glob pattern `*.php`.
S005-FR-004.a If the `HOME` environment variable cannot be resolved (all three sources return empty or false), the loader **MUST** silently skip the global-user location.
S005-FR-004.b If the directory does not exist, the loader **MUST** silently skip this location.
S005-FR-004.c Files **MUST** be loaded in alphabetical order, following the same contract as S005-FR-003.

### Composer Package Plugin Discovery

S005-FR-005 [P2] The loader **MUST** scan `{projectRoot}/vendor/composer/installed.json` for packages declaring `extra.parity.rules`.
S005-FR-005.a The `extra.parity.rules` value **MUST** be an array of fully qualified class name strings.
S005-FR-005.b The loader **MUST** handle both Composer v1 format (top-level array of packages) and Composer v2 format (packages nested under a `"packages"` key).
S005-FR-005.c Before instantiating any Composer plugin class, the loader **MUST** ensure the project's Composer autoloader (`{projectRoot}/vendor/autoload.php`) has been loaded via `require_once`.
S005-FR-005.d If `installed.json` does not exist, the loader **MUST** silently skip Composer plugin discovery.
S005-FR-005.e If `installed.json` contains malformed JSON, the loader **MUST** silently skip Composer plugin discovery (no fatal error).
S005-FR-005.f For each declared class name, the loader **MUST** verify that the class exists (via `class_exists()`) before instantiation.
S005-FR-005.g Each valid class **MUST** be instantiated with a no-argument constructor (`new $className`).
S005-FR-005.h After instantiation, the loader **MUST** verify the object implements `RuleInterface` before registering it.

### Plugin File Return Contract

S005-FR-006 [P1] A plugin PHP file loaded from a directory (project-local or global-user) **MUST** return one of the following:
S005-FR-006.a A single object implementing `RuleInterface` -- the loader **MUST** register it directly.
S005-FR-006.b An array of objects -- the loader **MUST** iterate the array and register each element that implements `RuleInterface`. Non-`RuleInterface` elements in the array **MUST** be silently skipped.
S005-FR-006.c Any other return value (including `null`, scalars, or objects not implementing `RuleInterface`) -- the loader **MUST** record a warning message identifying the file.

### RuleInterface Compliance Required

S005-FR-007 [P1] Every plugin rule **MUST** implement the `RuleInterface` contract as defined in S002-IF-001.
S005-FR-007.a Plugin rules **MUST** provide all six methods: `name()`, `parameters()`, `evaluate()`, `columnHeader()`, `formatCell()`, `isEnforced()`.
S005-FR-007.b Plugin rules **MUST** follow the same behavioral contracts as built-in rules (e.g., `name()` returns a unique string, `evaluate()` returns a `RuleResult`).
S005-FR-007.c Once registered, plugin rules **MUST** be resolved from `parity.yaml` configuration and evaluated identically to built-in rules -- no special handling.

### Error Handling and Warnings

S005-FR-008 [P1] Plugin loading errors **MUST NOT** cause Parity to terminate or throw unhandled exceptions.
S005-FR-008.a If a plugin file throws any `\Throwable` during `require`, the loader **MUST** catch it and record a warning containing the file path and the exception message.
S005-FR-008.b If a plugin file returns an invalid value (per S005-FR-006.c), the loader **MUST** record a warning containing the file path.
S005-FR-008.c If a Composer plugin class does not exist, the loader **MUST** record a warning containing the package name and class name.
S005-FR-008.d If a Composer plugin class throws during instantiation, the loader **MUST** catch the `\Throwable` and record a warning containing the package name, class name, and exception message.
S005-FR-008.e If a Composer plugin class does not implement `RuleInterface`, the loader **MUST** record a warning containing the package name and class name.
S005-FR-008.f All warnings **MUST** be retrievable via the `getWarnings()` method after loading completes.
S005-FR-008.g The `getWarnings()` method **MUST** return all accumulated warnings across all three discovery locations in a single array.

### Name Collision Resolution

S005-FR-009 [P1] When multiple plugins (or a plugin and a built-in rule) register the same `name()`, the last-registered rule **MUST** replace the previously registered one in the `RuleRegistry`.
S005-FR-009.a This behavior is inherited from S002-FR-004.e (RuleRegistry last-write-wins).
S005-FR-009.b Given the loading order (S005-FR-002), the effective precedence from lowest to highest is: built-in rules, project-local plugins, global-user plugins, Composer packages.
S005-FR-009.c No warning **MUST** be emitted when a name collision occurs -- this is intentional override behavior.

### Plugin-to-Registry Integration

S005-FR-010 [P1] The `PluginLoader` **MUST** call `RuleRegistry::register()` for each valid plugin rule discovered.
S005-FR-010.a After `loadAll()` completes, all successfully loaded plugin rules **MUST** be accessible via `RuleRegistry::get()`, `RuleRegistry::has()`, and `RuleRegistry::all()`.
S005-FR-010.b Plugin rules **MUST** participate in `RuleRegistry::resolve()` when referenced by name in `parity.yaml`.

### Composer Autoloader Bootstrapping

S005-FR-011 [P2] The loader **MUST** `require_once` the project's Composer autoloader (`{projectRoot}/vendor/autoload.php`) before attempting to instantiate any Composer plugin class.
S005-FR-011.a If the autoloader file does not exist, the loader **MUST** silently skip the `require_once` and proceed with class instantiation attempts (which will likely fail and produce warnings per S005-FR-008.c).

### Composer v1 and v2 installed.json Compatibility

S005-FR-012 [P2] The loader **MUST** support both Composer v1 format (where the top-level JSON value is an array of package objects) and Composer v2 format (where the top-level JSON value is an object with a `"packages"` key containing the array).
S005-FR-012.a The loader **MUST** check for the `"packages"` key first; if absent, treat the entire decoded JSON as the packages array.

### Alphabetical File Loading Within a Directory

S005-FR-013 [P1] Files discovered via `glob()` within a plugin directory **MUST** be sorted alphabetically (via PHP's `sort()`) before loading.
S005-FR-013.a This ensures deterministic loading order when multiple plugin files exist in the same directory.
S005-FR-013.b Combined with last-write-wins, this means `z-override.php` overrides `a-base.php` within the same directory if both register a rule with the same name.

### No Sandboxing

S005-FR-014 [P1] Plugin code **MUST** execute in the same PHP process and with the same privileges as Parity itself.
S005-FR-014.a There **MUST NOT** be any sandboxing, isolation, or permission restriction applied to plugin code.
S005-FR-014.b Plugins **MAY** access the filesystem, network, and any PHP extensions available to the Parity process.
S005-FR-014.c This is a deliberate design decision: Parity is a developer tool and plugins are code the developer explicitly installs.
