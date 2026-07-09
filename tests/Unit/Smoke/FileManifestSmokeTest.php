<?php
declare(strict_types=1);

namespace CertificateGenerator\Tests\Unit\Smoke;

use PHPUnit\Framework\TestCase;

/**
 * Smoke test: every file the bootstrap lazy-loads must actually exist on disk.
 *
 * certificate-generator.php degrades a missing entry to a silent admin notice
 * (see $missing_optional_files) rather than a fatal — so a typo'd or deleted
 * path can ship unnoticed. This parses the $critical_files / $optional_files
 * arrays straight out of the bootstrap source and checks each path resolves,
 * without executing the bootstrap itself (which requires a live WP runtime).
 */
class FileManifestSmokeTest extends TestCase {

    private static string $pluginRoot;
    private static string $bootstrapSource;

    public static function setUpBeforeClass(): void {
        self::$pluginRoot      = dirname(__DIR__, 3);
        self::$bootstrapSource = file_get_contents(self::$pluginRoot . '/certificate-generator.php');
    }

    /** @return array<string,string> path => description */
    private function extractFileArray(string $varName): array {
        $pattern = '/\$' . preg_quote($varName, '/') . '\s*=\s*array\s*\((.*?)\n\);/s';
        $this->assertMatchesRegularExpression($pattern, self::$bootstrapSource, "Could not find \${$varName} array in bootstrap file — has it been renamed?");
        preg_match($pattern, self::$bootstrapSource, $match);

        preg_match_all("/'([^']+)'\s*=>\s*'([^']*)'/", $match[1], $entries, PREG_SET_ORDER);
        $this->assertNotEmpty($entries, "\${$varName} array parsed as empty — regex likely out of sync with bootstrap formatting.");

        $files = [];
        foreach ($entries as $entry) {
            $files[$entry[1]] = $entry[2];
        }
        return $files;
    }

    public function test_all_critical_files_exist_on_disk(): void {
        foreach ($this->extractFileArray('critical_files') as $path => $description) {
            $this->assertFileExists(self::$pluginRoot . '/' . $path, "Critical file missing: {$path} ({$description})");
        }
    }

    public function test_all_optional_files_exist_on_disk(): void {
        foreach ($this->extractFileArray('optional_files') as $path => $description) {
            $this->assertFileExists(self::$pluginRoot . '/' . $path, "Optional file missing: {$path} ({$description})");
        }
    }

    public function test_no_duplicate_paths_between_critical_and_optional(): void {
        $critical = array_keys($this->extractFileArray('critical_files'));
        $optional = array_keys($this->extractFileArray('optional_files'));
        $overlap  = array_intersect($critical, $optional);
        $this->assertEmpty($overlap, 'File(s) listed in both critical and optional arrays: ' . implode(', ', $overlap));
    }

    public function test_every_listed_php_file_has_no_syntax_errors(): void {
        $all = array_merge(
            array_keys($this->extractFileArray('critical_files')),
            array_keys($this->extractFileArray('optional_files'))
        );

        foreach ($all as $path) {
            $full = self::$pluginRoot . '/' . $path;
            if (!file_exists($full)) {
                continue; // already reported by the exists tests above
            }
            $output   = [];
            $exitCode = 0;
            exec('php -l ' . escapeshellarg($full) . ' 2>&1', $output, $exitCode);
            $this->assertSame(0, $exitCode, "Syntax error in {$path}:\n" . implode("\n", $output));
        }
    }
}
