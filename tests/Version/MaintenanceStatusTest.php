<?php

namespace App\Tests\Version;

use App\Version\MaintenanceStatus;
use PHPUnit\Framework\TestCase;

class MaintenanceStatusTest extends TestCase
{
    public function testValuesArePublicContract(): void
    {
        self::assertSame(
            ['bugfix', 'security', 'critical-security', 'eol'],
            array_map(fn (MaintenanceStatus $status) => $status->value, MaintenanceStatus::cases()),
        );
    }

    public function testEveryCaseHasLabelAndDescription(): void
    {
        foreach (MaintenanceStatus::cases() as $status) {
            self::assertNotSame('', $status->label());
            self::assertNotSame('', $status->description());
        }

        self::assertSame('End of life', MaintenanceStatus::Eol->label());
    }
}
