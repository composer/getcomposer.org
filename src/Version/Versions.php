<?php

namespace App\Version;

use Composer\Pcre\Preg;

/**
 * Centralized definition of the Composer release channels exposed in
 * web/versions, including their maintenance metadata.
 *
 * This is the single source of truth for the lts / maintenance /
 * maintenance-until flags: it both generates the web/versions JSON (via
 * bin/generate-versions.php) and drives the maintenance policy section of the
 * download page (via HomeController::download()).
 */
class Versions
{
    /**
     * Minimum PHP version per release line, in Composer's numeric encoding
     * (e.g. 70205 = 7.2.5). Named after the PHP version so they stay meaningful
     * as new Composer lines are added.
     */
    private const MIN_PHP_5_3_0 = 50300;
    private const MIN_PHP_7_2_5 = 70205;

    /**
     * Per-line LTS metadata, keyed by the "major.minor" line. Multiple LTS lines
     * can be maintained in parallel: add a key here (and resolve its latest
     * version in update.sh) to introduce another one, e.g. '2.11' or '3.0'.
     *
     * @var array<string, array{min-php: int, maintenance: MaintenanceStatus, maintenance-until: string}>
     */
    private const LTS_LINES = [
        '2.2' => [
            'min-php' => self::MIN_PHP_5_3_0,
            'maintenance' => MaintenanceStatus::CriticalSecurity,
            'maintenance-until' => '2026-12-31',
        ],
    ];

    /**
     * Build the full web/versions structure from the version numbers resolved
     * by update.sh. `lts` is a list of the latest version of each maintained
     * LTS line (e.g. ['2.2.28'] today, ['2.11.5', '2.2.28'] once two LTS lines
     * coexist).
     *
     * @param array{stable: string, v1: string, lts: list<string>, preview: string, v2: string, v2Path: string, snapshot: string} $resolved
     *
     * @return array<array-key, list<array{path: string, version: string, min-php: int, lts: bool, maintenance: string, maintenance-until: string|null, eol?: true}>>
     */
    public static function generate(array $resolved): array
    {
        /** @var list<array{path: string, version: string, min-php: int, lts: bool, maintenance: string, maintenance-until: string|null, eol?: true}> $ltsEntries */
        $ltsEntries = [];
        /** @var array<string, list<array{path: string, version: string, min-php: int, lts: bool, maintenance: string, maintenance-until: string|null, eol?: true}>> $ltsChannels */
        $ltsChannels = [];
        foreach ($resolved['lts'] as $version) {
            $line = self::lineKey($version);
            if (!isset(self::LTS_LINES[$line])) {
                throw new \InvalidArgumentException(
                    "No LTS metadata defined for line $line (version $version). Add it to ".self::class."::LTS_LINES."
                );
            }
            $meta = self::LTS_LINES[$line];
            $entry = self::entry(
                '/download/'.$version.'/composer.phar',
                $version,
                $meta['min-php'],
                true,
                $meta['maintenance'],
                $meta['maintenance-until'],
            );
            $ltsEntries[] = $entry;
            $ltsChannels[$line] = [$entry];
        }

        // Active releases are maintained until at least the next feature release;
        // this rolling minimum should be bumped whenever a new feature release ships.
        $stable = self::activeEntry('/download/'.$resolved['stable'].'/composer.phar', $resolved['stable']);
        $preview = self::activeEntry('/download/'.$resolved['preview'].'/composer.phar', $resolved['preview']);
        $snapshot = self::activeEntry('/composer.phar', $resolved['snapshot']);
        $v2 = self::activeEntry($resolved['v2Path'], $resolved['v2']);

        $v1 = self::entry(
            '/download/'.$resolved['v1'].'/composer.phar',
            $resolved['v1'],
            self::MIN_PHP_5_3_0,
            false,
            MaintenanceStatus::Eol,
            '2026-05-30',
        );

        return [
            'stable' => [$stable, ...$ltsEntries],
            'preview' => [$preview, ...$ltsEntries],
            'snapshot' => [$snapshot, ...$ltsEntries],
            ...$ltsChannels,
            '2' => [$v2, ...$ltsEntries],
            '1' => [$v1],
        ];
    }

    /**
     * Derive the rows shown in the download page's maintenance policy section
     * from loaded version data, so the page never hardcodes which line is LTS.
     * One row per distinct release line, newest first. Snapshot/dev entries are
     * skipped (their "version" is a commit hash, not a release line).
     *
     * @param array<string, array<int, array{path: string, version: string, min-php: int, lts: bool, maintenance: string, maintenance-until: string|null, eol?: true}>> $versionData
     *
     * @return list<array{line: string, lts: bool, status: MaintenanceStatus, until: string|null}>
     */
    public static function policyRows(array $versionData): array
    {
        $byLine = [];
        foreach ($versionData as $entries) {
            foreach ($entries as $entry) {
                if (!Preg::isMatch('{^\d+\.\d+\.}', $entry['version'])) {
                    continue;
                }
                $line = self::lineKey($entry['version']);
                $byLine[$line] = [
                    'line' => $line.'.x',
                    'lts' => $entry['lts'],
                    'status' => MaintenanceStatus::from($entry['maintenance']),
                    'until' => $entry['maintenance-until'],
                ];
            }
        }

        uksort($byLine, fn (string $a, string $b): int => version_compare($b, $a));

        return array_values($byLine);
    }

    /**
     * @return array{path: string, version: string, min-php: int, lts: bool, maintenance: string, maintenance-until: string|null, eol?: true}
     */
    private static function activeEntry(string $path, string $version): array
    {
        return self::entry($path, $version, self::MIN_PHP_7_2_5, false, MaintenanceStatus::Bugfix, null);
    }

    /**
     * @return array{path: string, version: string, min-php: int, lts: bool, maintenance: string, maintenance-until: string|null, eol?: true}
     */
    private static function entry(string $path, string $version, int $minPhp, bool $lts, MaintenanceStatus $maintenance, ?string $until): array
    {
        $entry = [
            'path' => $path,
            'version' => $version,
            'min-php' => $minPhp,
            'lts' => $lts,
            'maintenance' => $maintenance->value,
            'maintenance-until' => $until,
        ];

        // BC: Composer <= 2.10.1 does not read the `maintenance` field; its
        // self-update detects EOL via isset($latest['eol']). Emit `eol: true`
        // only for EOL lines so those clients keep warning. The key must be
        // omitted otherwise — isset() treats any present key (even false) as set.
        if ($maintenance === MaintenanceStatus::Eol) {
            $entry['eol'] = true;
        }

        return $entry;
    }

    /**
     * Reduce a version like "2.2.28" to its "major.minor" line key "2.2". Falls
     * back to the raw string when it does not look like a release version.
     */
    private static function lineKey(string $version): string
    {
        if (Preg::isMatchStrictGroups('{^(\d+\.\d+)\.}', $version, $matches)) {
            return $matches[1];
        }

        return $version;
    }
}
