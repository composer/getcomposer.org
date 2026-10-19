<?php

namespace App\Tests\Version;

use App\Version\MaintenanceStatus;
use App\Version\Versions;
use PHPUnit\Framework\TestCase;

class VersionsTest extends TestCase
{
    private const RESOLVED = [
        'stable' => '2.10.3',
        'v1' => '1.10.27',
        'lts' => ['2.2.30'],
        'preview' => '2.11.0-RC1',
        'v2' => '2.10.3',
        'v2Path' => '/download/2.10.3/composer.phar',
        'snapshot' => '0123456789abcdef0123456789abcdef01234567',
    ];

    public function testGenerate(): void
    {
        $versions = Versions::generate(self::RESOLVED);

        self::assertSame(['stable', 'preview', 'snapshot', '2.2', 2, 1], array_keys($versions));

        $lts = [
            'path' => '/download/2.2.30/composer.phar',
            'version' => '2.2.30',
            'min-php' => 50300,
            'lts' => true,
            'maintenance' => 'critical-security',
            'maintenance-until' => '2026-12-31',
        ];

        self::assertSame([
            [
                'path' => '/download/2.10.3/composer.phar',
                'version' => '2.10.3',
                'min-php' => 70205,
                'lts' => false,
                'maintenance' => 'bugfix',
                'maintenance-until' => null,
            ],
            $lts,
        ], $versions['stable']);

        self::assertSame('2.11.0-RC1', $versions['preview'][0]['version']);
        self::assertSame($lts, $versions['preview'][1]);
        self::assertSame('/composer.phar', $versions['snapshot'][0]['path']);
        self::assertSame(self::RESOLVED['snapshot'], $versions['snapshot'][0]['version']);
        self::assertSame([$lts], $versions['2.2']);
        self::assertSame('/download/2.10.3/composer.phar', $versions[2][0]['path']);
        self::assertSame($lts, $versions[2][1]);
    }

    public function testEolFlagOnlyEmittedForEolLines(): void
    {
        $versions = Versions::generate(self::RESOLVED);

        self::assertSame([[
            'path' => '/download/1.10.27/composer.phar',
            'version' => '1.10.27',
            'min-php' => 50300,
            'lts' => false,
            'maintenance' => 'eol',
            'maintenance-until' => '2026-05-30',
            'eol' => true,
        ]], $versions[1]);

        foreach (['stable', 'preview', 'snapshot', '2.2', 2] as $channel) {
            foreach ($versions[$channel] as $entry) {
                self::assertArrayNotHasKey('eol', $entry);
            }
        }
    }

    public function testGenerateRejectsUnknownLtsLine(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('No LTS metadata defined for line 2.9');

        Versions::generate(['lts' => ['2.9.1']] + self::RESOLVED);
    }

    public function testPolicyRows(): void
    {
        $rows = Versions::policyRows(Versions::generate(self::RESOLVED));

        self::assertSame([
            ['line' => '2.11.x', 'lts' => false, 'status' => MaintenanceStatus::Bugfix, 'until' => null],
            ['line' => '2.10.x', 'lts' => false, 'status' => MaintenanceStatus::Bugfix, 'until' => null],
            ['line' => '2.2.x', 'lts' => true, 'status' => MaintenanceStatus::CriticalSecurity, 'until' => '2026-12-31'],
            ['line' => '1.10.x', 'lts' => false, 'status' => MaintenanceStatus::Eol, 'until' => '2026-05-30'],
        ], $rows);
    }
}
