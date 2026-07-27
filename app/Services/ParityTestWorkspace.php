<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Filesystem\Filesystem;
use JsonException;
use RuntimeException;

/**
 * Specs: S011
 *
 * Owns the temporary coverage and report paths used by `parity test`.
 */
final class ParityTestWorkspace
{
    private readonly Filesystem $files;

    private readonly string $projectRoot;

    public function __construct(string $projectRoot, ?Filesystem $files = null)
    {
        $this->files = $files ?? new Filesystem;
        $this->projectRoot = $this->canonicalizePath($projectRoot);
    }

    public function reportTarget(string $configuredPath): string
    {
        $this->assertNoSymlinkComponents($configuredPath, 'report directory');

        $reportsDir = $this->canonicalizePath($configuredPath);
        if ($this->isProtectedDirectory($reportsDir)) {
            throw new RuntimeException("Refusing unsafe report directory: {$reportsDir}");
        }
        if (file_exists($reportsDir) && ! is_dir($reportsDir)) {
            throw new RuntimeException("Report output path exists and is not a directory: {$reportsDir}");
        }
        if (! is_dir($reportsDir) || $this->directoryIsEmpty($reportsDir)) {
            return $reportsDir;
        }

        $reservedDir = $this->canonicalizePath($this->projectRoot.'/.parity');
        if ($this->isPathWithin($reportsDir, $reservedDir) || $this->isParityReportDirectory($reportsDir)) {
            return $reportsDir;
        }

        throw new RuntimeException("Refusing to replace non-Parity directory: {$reportsDir}");
    }

    public function createStagingDirectory(string $reportsDir): string
    {
        $this->assertNoSymlinkComponents($reportsDir, 'report directory');

        try {
            $this->files->ensureDirectoryExists(dirname($reportsDir));
            do {
                $stagingDir = $reportsDir.'.tmp-'.bin2hex(random_bytes(6));
            } while (file_exists($stagingDir));
            $this->files->ensureDirectoryExists($stagingDir.'/reports');
        } catch (\Throwable $e) {
            throw new RuntimeException("Could not create report staging directory: {$e->getMessage()}", previous: $e);
        }

        return $stagingDir;
    }

    public function prepareCoverageArtifact(string $configuredPath, ?string $reportsDir = null): string
    {
        $this->assertNoSymlinkComponents($configuredPath, 'coverage artifact');

        $path = $this->canonicalizePath($configuredPath);
        if ($this->isProtectedDirectory($path)) {
            throw new RuntimeException("Refusing unsafe coverage artifact path: {$path}");
        }
        if ($reportsDir !== null) {
            $reportsDir = $this->canonicalizePath($reportsDir);
            if ($path === $reportsDir
                || $this->isPathWithin($path, $reportsDir)
                || $this->isPathWithin($reportsDir, $path)) {
                throw new RuntimeException("Coverage artifact must not overlap the report directory: {$path}");
            }
        }

        $reservedDir = $this->canonicalizePath($this->projectRoot.'/.parity');
        $owned = $this->isPathWithin($path, $reservedDir) || is_file($this->coverageMarkerPath($path));
        if (file_exists($path) && ! $owned) {
            throw new RuntimeException("Refusing to replace unowned coverage artifact: {$path}");
        }

        try {
            if (is_dir($path)) {
                $this->files->deleteDirectory($path);
            } elseif (is_file($path) && ! unlink($path)) {
                throw new RuntimeException("Could not remove previous coverage artifact: {$path}");
            }

            if ($this->looksLikeDirectoryTarget($path)) {
                $this->files->ensureDirectoryExists($path);
            } else {
                $this->files->ensureDirectoryExists(dirname($path));
            }

            if (file_put_contents($this->coverageMarkerPath($path), "parity-test-artifact\n") === false) {
                throw new RuntimeException("Could not mark coverage artifact path as Parity-owned: {$path}");
            }
        } catch (RuntimeException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new RuntimeException("Could not prepare coverage artifact [{$path}]: {$e->getMessage()}", previous: $e);
        }

        return $path;
    }

    public function removePreparedCoverageArtifact(string $path): void
    {
        if (is_link($path)) {
            @unlink($path);
        } elseif (is_dir($path)) {
            $this->files->deleteDirectory($path);
        } elseif (is_file($path)) {
            @unlink($path);
        }

        $marker = $this->coverageMarkerPath($path);
        if (is_file($marker)) {
            @unlink($marker);
        }
    }

    public function writeJsonFile(string $path, array $data): void
    {
        try {
            $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n";
        } catch (JsonException $e) {
            throw new RuntimeException("Could not encode report JSON: {$e->getMessage()}", previous: $e);
        }

        if (file_put_contents($path, $json) === false) {
            throw new RuntimeException("Could not write report file: {$path}");
        }
    }

    public function publishReports(string $stagingDir, string $reportsDir): ?string
    {
        $this->assertNoSymlinkComponents($reportsDir, 'report directory');

        $backupDir = null;
        if (is_dir($reportsDir)) {
            $backupDir = $reportsDir.'.previous-'.bin2hex(random_bytes(6));
            if (! @rename($reportsDir, $backupDir)) {
                throw new RuntimeException("Could not preserve previous report directory: {$reportsDir}");
            }
        }

        if (! @rename($stagingDir, $reportsDir)) {
            if ($backupDir !== null) {
                @rename($backupDir, $reportsDir);
            }

            throw new RuntimeException("Could not move completed reports into place: {$reportsDir}");
        }

        if ($backupDir !== null && is_dir($backupDir) && ! $this->files->deleteDirectory($backupDir)) {
            return "Completed reports are active, but the previous report directory could not be removed: {$backupDir}";
        }

        return null;
    }

    public function cleanupDirectory(string $path): void
    {
        if (is_dir($path)) {
            $this->files->deleteDirectory($path);
        }
    }

    public function reportHasExecutableCoverage(array $report): bool
    {
        foreach (($report['files'] ?? []) as $file) {
            if (is_array($file) && (int) ($file['totalExecutableLines'] ?? 0) > 0) {
                return true;
            }
        }

        return false;
    }

    private function coverageMarkerPath(string $path): string
    {
        return $path.'.parity-test-artifact';
    }

    private function assertNoSymlinkComponents(string $path, string $label): void
    {
        $candidate = str_replace('\\', '/', $path);
        while (true) {
            if (is_link($candidate)) {
                throw new RuntimeException("Refusing symlink component in {$label}: {$candidate}");
            }

            $parent = dirname($candidate);
            if ($parent === $candidate) {
                return;
            }
            $candidate = $parent;
        }
    }

    private function isProtectedDirectory(string $path): bool
    {
        $normalized = rtrim($this->canonicalizePath($path), '/');
        $root = rtrim($this->projectRoot, '/');
        $reservedDir = rtrim($this->canonicalizePath($this->projectRoot.'/.parity'), '/');
        $home = getenv('HOME');
        $protected = [$root, $reservedDir];
        if (is_string($home) && $home !== '') {
            $protected[] = rtrim($this->canonicalizePath($home), '/');
        }

        return $normalized === ''
            || dirname($normalized) === $normalized
            || preg_match('#^[A-Za-z]:$#', $normalized) === 1
            || in_array($normalized, $protected, true);
    }

    private function isPathWithin(string $path, string $parent): bool
    {
        $path = rtrim(str_replace('\\', '/', $path), '/');
        $parent = rtrim(str_replace('\\', '/', $parent), '/');

        return $path !== $parent && str_starts_with($path, $parent.'/');
    }

    private function directoryIsEmpty(string $path): bool
    {
        $entries = scandir($path);

        return $entries !== false && count($entries) === 2;
    }

    private function isParityReportDirectory(string $path): bool
    {
        $indexPath = $path.'/index.json';
        if (! is_file($indexPath)) {
            return false;
        }

        $contents = file_get_contents($indexPath);
        if ($contents === false) {
            return false;
        }

        $manifest = json_decode($contents, true);

        return is_array($manifest)
            && ($manifest['version'] ?? null) === 1
            && ($manifest['kind'] ?? null) === 'parity-per-test-coverage';
    }

    private function canonicalizePath(string $path): string
    {
        $path = str_replace('\\', '/', $path);
        $real = realpath($path);
        if ($real !== false) {
            return str_replace('\\', '/', $real);
        }

        $segments = [];
        $prefix = str_starts_with($path, '/') ? '/' : '';
        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }
            if ($segment === '..') {
                array_pop($segments);

                continue;
            }
            $segments[] = $segment;
        }

        return $prefix.implode('/', $segments);
    }

    private function looksLikeDirectoryTarget(string $path): bool
    {
        return ! str_contains(basename($path), '.');
    }
}
