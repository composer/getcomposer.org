<?php

namespace App\Tests\Bin;

use App\Version\Versions;
use PHPUnit\Framework\TestCase;

class GenerateVersionsTest extends TestCase
{
    public function testOutputMatchesVersionsGenerate(): void
    {
        [$exitCode, $output] = $this->runScript([
            'stable=2.10.3', 'v1=1.10.27', 'v2_2_lts=2.2.30', 'preview=2.11.0-RC1',
            'v2=2.10.3', 'v2Path=/download/2.10.3/composer.phar', 'snapshot=abcdef',
        ]);

        self::assertSame(0, $exitCode);
        self::assertSame(
            json_decode((string) json_encode(Versions::generate([
                'stable' => '2.10.3',
                'v1' => '1.10.27',
                'lts' => ['2.2.30'],
                'preview' => '2.11.0-RC1',
                'v2' => '2.10.3',
                'v2Path' => '/download/2.10.3/composer.phar',
                'snapshot' => 'abcdef',
            ])), true),
            json_decode($output, true),
        );
    }

    public function testFailsOnMissingArguments(): void
    {
        [$exitCode, $output] = $this->runScript(['stable=2.10.3']);

        self::assertSame(1, $exitCode);
        self::assertStringContainsString('Missing required argument(s): v1, preview, v2, v2Path, snapshot', $output);
    }

    public function testFailsWithoutLtsArgument(): void
    {
        [$exitCode, $output] = $this->runScript([
            'stable=2.10.3', 'v1=1.10.27', 'preview=2.11.0-RC1', 'v2=2.10.3',
            'v2Path=/download/2.10.3/composer.phar', 'snapshot=abcdef',
        ]);

        self::assertSame(1, $exitCode);
        self::assertStringContainsString('at least one *_lts', $output);
    }

    /**
     * @param list<string> $args
     *
     * @return array{int, string}
     */
    private function runScript(array $args): array
    {
        $cmd = array_merge([PHP_BINARY, dirname(__DIR__, 2).'/bin/generate-versions.php'], $args);
        $proc = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        self::assertIsResource($proc);
        $output = stream_get_contents($pipes[1]).stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        return [proc_close($proc), $output];
    }
}
