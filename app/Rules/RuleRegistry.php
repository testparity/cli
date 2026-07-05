<?php

declare(strict_types=1);

namespace App\Rules;

/**
 * Specs: S002, S005, S006
 *
 * Registry for parity rules. Rules are registered as container singletons
 * under the key "parity.rules.{name}" and can be resolved by name.
 */
class RuleRegistry
{
    /** @var array<string, RuleInterface> */
    private array $rules = [];

    public function register(RuleInterface $rule): void
    {
        $this->rules[$rule->name()] = $rule;
    }

    public function get(string $name): ?RuleInterface
    {
        return $this->rules[$name] ?? null;
    }

    public function has(string $name): bool
    {
        return isset($this->rules[$name]);
    }

    /**
     * @return array<string, RuleInterface>
     */
    public function all(): array
    {
        return $this->rules;
    }

    /**
     * Resolve a list of rule configs from parity.yaml into rule instances with validated params.
     *
     * Supports two formats:
     *   - String: "test-exists" (no params)
     *   - Map:    { minimum-coverage: { min: 80 } }
     *
     * @param  list<string|array<string, mixed>>  $ruleConfigs
     * @return list<array{rule: RuleInterface, params: array<string, mixed>}>
     *
     * @throws \InvalidArgumentException if a rule is unknown or params are invalid
     */
    public function resolve(array $ruleConfigs): array
    {
        $resolved = [];

        foreach ($ruleConfigs as $config) {
            if (is_string($config)) {
                $name = $config;
                $params = [];
            } elseif (is_array($config)) {
                if (isset($config['name'])) {
                    $name = $config['name'];
                    $params = array_diff_key($config, ['name' => true]);
                } else {
                    $name = array_key_first($config);
                    $params = is_array($config[$name]) ? $config[$name] : [];
                }
            } else {
                continue;
            }

            $rule = $this->get($name);
            if ($rule === null) {
                throw new \InvalidArgumentException("Unknown parity rule: '{$name}'. Available: ".implode(', ', array_keys($this->rules)));
            }

            // Validate params against rule's parameter spec
            $paramSpec = $rule->parameters();
            if ($paramSpec !== []) {
                $this->validateParams($name, $params, $paramSpec);
            }

            $resolved[] = ['rule' => $rule, 'params' => $params];
        }

        return $resolved;
    }

    /**
     * Validate params against a rule's parameter spec.
     * Uses simple validation without requiring laravel/validation.
     *
     * @throws \InvalidArgumentException
     */
    private function validateParams(string $ruleName, array $params, array $paramSpec): void
    {
        foreach ($paramSpec as $key => $spec) {
            // Skip nested validators (e.g. 'linkers.*')
            if (str_contains($key, '.')) {
                continue;
            }

            $specParts = is_string($spec) ? explode('|', $spec) : $spec;
            $isRequired = in_array('required', $specParts, true);
            $value = $params[$key] ?? null;

            if ($isRequired && $value === null) {
                throw new \InvalidArgumentException("Rule '{$ruleName}' requires parameter '{$key}'");
            }

            if ($value === null) {
                continue;
            }

            if (in_array('numeric', $specParts, true) && ! is_numeric($value)) {
                throw new \InvalidArgumentException("Rule '{$ruleName}' parameter '{$key}' must be numeric");
            }

            if (in_array('string', $specParts, true) && ! is_string($value)) {
                throw new \InvalidArgumentException("Rule '{$ruleName}' parameter '{$key}' must be a string");
            }

            if (in_array('array', $specParts, true) && ! is_array($value)) {
                throw new \InvalidArgumentException("Rule '{$ruleName}' parameter '{$key}' must be an array");
            }

            foreach ($specParts as $part) {
                if (str_starts_with($part, 'min:') && is_numeric($value)) {
                    $minimum = (float) substr($part, 4);
                    if ((float) $value < $minimum) {
                        throw new \InvalidArgumentException("Rule '{$ruleName}' parameter '{$key}' must be at least {$minimum}");
                    }
                }

                if (str_starts_with($part, 'max:') && is_numeric($value)) {
                    $maximum = (float) substr($part, 4);
                    if ((float) $value > $maximum) {
                        throw new \InvalidArgumentException("Rule '{$ruleName}' parameter '{$key}' must be at most {$maximum}");
                    }
                }
            }
        }
    }
}
