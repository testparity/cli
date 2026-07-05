<?php

declare(strict_types=1);

namespace Tests\Unit\Rules;

use App\Rules\RuleResult;
use PHPUnit\Framework\TestCase;

class RuleResultTest extends TestCase
{
    public function test_creates_passing_result(): void
    {
        $result = RuleResult::pass('Y');

        expect($result->passed)->toBeTrue();
        expect($result->value)->toBe('Y');
        expect($result->error)->toBeNull();
    }

    public function test_creates_failing_result(): void
    {
        $result = RuleResult::fail('below threshold', '42%');

        expect($result->passed)->toBeFalse();
        expect($result->value)->toBe('42%');
        expect($result->error)->toBe('below threshold');
    }

    public function test_creates_skipped_result_as_non_failure(): void
    {
        $result = RuleResult::skip('-');

        expect($result->passed)->toBeTrue();
        expect($result->value)->toBe('-');
        expect($result->error)->toBeNull();
    }
}
