<?php

declare(strict_types=1);

namespace Tests\Unit\Actions;

use App\Actions\ExportData;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ExportData::class)]
class ExportDataTest extends TestCase
{
    public function test_handle_returns_true(): void
    {
        $action = new ExportData;
        $this->assertTrue($action->handle());
    }
}
