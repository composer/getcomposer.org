<?php

namespace App\Tests\Version;

use App\Version\MaintenanceStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class MaintenanceStatusTest extends TestCase
{
    #[DataProvider('providePublicValues')]
    public function testValueIsPublicContract(string $value, MaintenanceStatus $expected): void
    {
        self::assertSame($expected, MaintenanceStatus::tryFrom($value));
    }

    public function testPublicValuesCoverAllCases(): void
    {
        $covered = array_map(fn (array $args) => $args[1], iterator_to_array(self::providePublicValues(), false));

        self::assertSame(MaintenanceStatus::cases(), $covered);
    }

    /**
     * @return iterable<string, array{string, MaintenanceStatus}>
     */
    public static function providePublicValues(): iterable
    {
        yield 'bugfix' => ['bugfix', MaintenanceStatus::Bugfix];
        yield 'security' => ['security', MaintenanceStatus::Security];
        yield 'critical-security' => ['critical-security', MaintenanceStatus::CriticalSecurity];
        yield 'eol' => ['eol', MaintenanceStatus::Eol];
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
