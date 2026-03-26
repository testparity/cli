<?php

declare(strict_types=1);

namespace App\Rules;

/**
 * Result of evaluating a single rule against a source file.
 */
class RuleResult
{
    public function __construct(
        public readonly bool $passed,
        public readonly ?string $value = null,
        public readonly ?string $error = null,
    ) {}

    public static function pass(?string $value = null): self
    {
        return new self(passed: true, value: $value);
    }

    public static function fail(string $error, ?string $value = null): self
    {
        return new self(passed: false, value: $value, error: $error);
    }

    public static function skip(?string $value = null): self
    {
        return new self(passed: true, value: $value);
    }
}
