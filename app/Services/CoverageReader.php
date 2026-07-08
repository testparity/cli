<?php

declare(strict_types=1);

namespace App\Services;

use DOMDocument;
use DOMXPath;

/**
 * Specs: S003
 *
 * Reads single-file XML coverage reports and returns per-file coverage percentages.
 *
 * Clover and Cobertura XML do not store which test executed which line. They store per-file or per-line counts
 * (see vendor/phpunit/php-code-coverage Report/Clover.php: it uses count($coverageData[$line]) and
 * never writes test names). For per-test coverage (which test covered which line), PHPUnit's own
 * XML format (--coverage-xml) stores <covered by="TestClass::method"/> per line.
 */
class CoverageReader
{
    /**
     * Parse a Clover or Cobertura XML file and return coverage percentage per file path.
     * Keys are normalized absolute paths and, when projectRoot is given, relative paths.
     * Use projectRoot so lookup works whether the XML stores absolute or relative paths.
     *
     * @return array<string, float> path => coverage percent (0–100)
     */
    public function read(string $coverageXmlPath, ?string $projectRoot = null): array
    {
        if (! is_file($coverageXmlPath)) {
            return [];
        }

        $content = @file_get_contents($coverageXmlPath);
        if ($content === false) {
            return [];
        }

        $doc = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $loaded = $doc->loadXML($content);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        if (! $loaded) {
            return [];
        }

        $xpath = new DOMXPath($doc);
        $map = [];
        $root = $projectRoot !== null ? $this->normalizePath($projectRoot) : null;

        $files = $xpath->query('//file[@name]');
        foreach ($files as $file) {
            $name = $file->getAttribute('path') ?: $file->getAttribute('name');
            if ($name === '') {
                continue;
            }

            $metricsList = $xpath->query('metrics', $file);
            $metrics = $metricsList->length > 0 ? $metricsList->item($metricsList->length - 1) : null;
            if ($metrics === null) {
                continue;
            }

            $statements = (int) $metrics->getAttribute('statements');
            $covered = (int) $metrics->getAttribute('coveredstatements');

            $percent = $statements > 0
                ? round(100.0 * $covered / $statements, 2)
                : 100.0;

            $this->storeCoveragePath($map, $name, $percent, $root);
        }

        $classes = $xpath->query('//class[@filename]');
        foreach ($classes as $class) {
            $filename = $class->getAttribute('filename');
            if ($filename === '') {
                continue;
            }

            $lineRate = $class->getAttribute('line-rate');
            $percent = $lineRate !== ''
                ? round(((float) $lineRate) * 100, 2)
                : $this->readCoberturaLinePercent($xpath, $class);

            $this->storeCoveragePath($map, $filename, $percent, $root);
        }

        return $map;
    }

    /**
     * Read project-level (global) coverage percentage from Clover or Cobertura XML.
     * Uses Clover project metrics when present, otherwise Cobertura's root line-rate.
     *
     * @return float|null 0–100 or null if not found
     */
    public function readGlobalCoverage(string $coverageXmlPath): ?float
    {
        if (! is_file($coverageXmlPath)) {
            return null;
        }

        $content = @file_get_contents($coverageXmlPath);
        if ($content === false) {
            return null;
        }

        $doc = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $loaded = $doc->loadXML($content);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        if (! $loaded) {
            return null;
        }

        $xpath = new DOMXPath($doc);
        $metrics = $xpath->query('//metrics[@files]')->item(0);
        if ($metrics !== null) {
            $statements = (int) $metrics->getAttribute('statements');
            $covered = (int) $metrics->getAttribute('coveredstatements');

            if ($statements <= 0) {
                return 100.0;
            }

            return round(100.0 * $covered / $statements, 2);
        }

        $coverage = $xpath->query('/*[local-name()="coverage"]')->item(0);
        if ($coverage !== null && $coverage->hasAttribute('line-rate')) {
            return round(((float) $coverage->getAttribute('line-rate')) * 100, 2);
        }

        return null;
    }

    /**
     * Parse executable and covered line numbers from Clover or Cobertura XML.
     *
     * @return array{
     *     coveredLines: array<string, list<int>>,
     *     totalExecutable: array<string, int>
     * }
     */
    public function readExecutableLines(string $coverageXmlPath, ?string $projectRoot = null): array
    {
        if (! is_file($coverageXmlPath)) {
            return ['coveredLines' => [], 'totalExecutable' => []];
        }

        $content = @file_get_contents($coverageXmlPath);
        if ($content === false) {
            return ['coveredLines' => [], 'totalExecutable' => []];
        }

        $doc = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $loaded = $doc->loadXML($content);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        if (! $loaded) {
            return ['coveredLines' => [], 'totalExecutable' => []];
        }

        $xpath = new DOMXPath($doc);
        $coveredLines = [];
        $totalExecutable = [];
        $root = $projectRoot !== null ? $this->normalizePath($projectRoot) : null;

        $files = $xpath->query('//file[@name]');
        foreach ($files as $file) {
            $name = $file->getAttribute('path') ?: $file->getAttribute('name');
            if ($name === '') {
                continue;
            }

            $executableLines = [];
            $covered = [];
            $lineNodes = $xpath->query('line', $file);
            foreach ($lineNodes as $lineNode) {
                $type = $lineNode->getAttribute('type');
                if ($type !== '' && $type !== 'stmt') {
                    continue;
                }

                $num = $lineNode->getAttribute('num');
                if ($num === '') {
                    continue;
                }

                $line = (int) $num;
                $executableLines[$line] = true;
                if ((int) $lineNode->getAttribute('count') > 0) {
                    $covered[$line] = true;
                }
            }

            $this->storeExecutableCoverage($coveredLines, $totalExecutable, $name, array_keys($covered), count($executableLines), $root);
        }

        $classes = $xpath->query('//class[@filename]');
        foreach ($classes as $class) {
            $filename = $class->getAttribute('filename');
            if ($filename === '') {
                continue;
            }

            $executableLines = [];
            $covered = [];
            $lineNodes = $xpath->query('lines/line', $class);
            foreach ($lineNodes as $lineNode) {
                $num = $lineNode->getAttribute('number') ?: $lineNode->getAttribute('nr');
                if ($num === '') {
                    continue;
                }

                $line = (int) $num;
                $executableLines[$line] = true;
                if ((int) $lineNode->getAttribute('hits') > 0) {
                    $covered[$line] = true;
                }
            }

            $this->storeExecutableCoverage($coveredLines, $totalExecutable, $filename, array_keys($covered), count($executableLines), $root);
        }

        return ['coveredLines' => $coveredLines, 'totalExecutable' => $totalExecutable];
    }

    private function readCoberturaLinePercent(DOMXPath $xpath, \DOMNode $class): float
    {
        $lines = $xpath->query('lines/line', $class);
        if ($lines->length === 0) {
            return 100.0;
        }

        $covered = 0;
        foreach ($lines as $line) {
            if ((int) $line->getAttribute('hits') > 0) {
                $covered++;
            }
        }

        return round(100.0 * $covered / $lines->length, 2);
    }

    /**
     * @param  array<string, list<int>>  $coveredLines
     * @param  array<string, int>  $totalExecutable
     * @param  list<int>  $covered
     */
    private function storeExecutableCoverage(array &$coveredLines, array &$totalExecutable, string $path, array $covered, int $executable, ?string $root): void
    {
        $normalized = $this->normalizePath($path);
        $keys = [$normalized];

        if ($root !== null) {
            $relative = $this->pathRelativeTo($normalized, $root);
            if ($relative === null && $this->isRelativePath($path)) {
                $relative = str_replace('\\', '/', $path);
            }
            if ($relative !== null) {
                $keys[] = $relative;
            }
        }

        sort($covered);
        foreach ($keys as $key) {
            $coveredLines[$key] = $covered;
            $totalExecutable[$key] = $executable;
        }
    }

    /**
     * @param  array<string, float>  $map
     */
    private function storeCoveragePath(array &$map, string $path, float $percent, ?string $root): void
    {
        $normalized = $this->normalizePath($path);
        $map[$normalized] = $percent;

        if ($root === null) {
            return;
        }

        $relative = $this->pathRelativeTo($normalized, $root);
        if ($relative === null && $this->isRelativePath($path)) {
            $relative = str_replace('\\', '/', $path);
        }
        if ($relative !== null) {
            $map[$relative] = $percent;
        }
    }

    private function isRelativePath(string $path): bool
    {
        return $path !== '' && $path[0] !== '/' && (strlen($path) < 2 || $path[1] !== ':');
    }

    private function normalizePath(string $path): string
    {
        $path = str_replace('\\', '/', $path);
        $real = realpath($path);

        return $real !== false ? $real : $path;
    }

    /** Path relative to root (forward slashes, no leading slash), or null if not under root. */
    private function pathRelativeTo(string $absolutePath, string $root): ?string
    {
        $path = str_replace('\\', '/', $absolutePath);
        $root = rtrim(str_replace('\\', '/', $root), '/');
        if ($path === $root) {
            return '';
        }
        if ($root !== '' && str_starts_with($path, $root.'/')) {
            return substr($path, strlen($root) + 1);
        }
        if ($root === '' && preg_match('#^[a-zA-Z]:/#', $path)) {
            return $path;
        }

        return null;
    }
}
