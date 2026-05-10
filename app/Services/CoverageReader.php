<?php

declare(strict_types=1);

namespace App\Services;

use DOMDocument;
use DOMXPath;

/**
 * Reads Clover XML coverage reports (PHPUnit --coverage-clover) and returns per-file coverage percentages.
 *
 * Clover XML does not store which test executed which line. It only stores per-line execution count
 * (see vendor/phpunit/php-code-coverage Report/Clover.php: it uses count($coverageData[$line]) and
 * never writes test names). For per-test coverage (which test covered which line), PHPUnit's own
 * XML format (--coverage-xml) stores <covered by="TestClass::method"/> per line.
 */
class CoverageReader
{
    /**
     * Parse a Clover XML file and return coverage percentage per file path.
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
        if (! @$doc->loadXML($content)) {
            return [];
        }

        $xpath = new DOMXPath($doc);
        $files = $xpath->query('//file[@name]');
        $map = [];
        $root = $projectRoot !== null ? $this->normalizePath($projectRoot) : null;

        foreach ($files as $file) {
            $name = $file->getAttribute('name');
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

            $normalized = $this->normalizePath($name);
            $map[$normalized] = $percent;

            if ($root !== null) {
                $relative = $this->pathRelativeTo($normalized, $root);
                if ($relative === null && $name !== '' && $name[0] !== '/' && (strlen($name) < 2 || $name[1] !== ':')) {
                    $relative = str_replace('\\', '/', $name);
                }
                if ($relative !== null) {
                    $map[$relative] = $percent;
                }
            }
        }

        return $map;
    }

    /**
     * Read project-level (global) coverage percentage from Clover XML.
     * Uses the project metrics element (statements / coveredstatements).
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
        if (! @$doc->loadXML($content)) {
            return null;
        }

        $xpath = new DOMXPath($doc);
        $metrics = $xpath->query('//metrics[@files]')->item(0);
        if ($metrics === null) {
            return null;
        }

        $statements = (int) $metrics->getAttribute('statements');
        $covered = (int) $metrics->getAttribute('coveredstatements');

        if ($statements <= 0) {
            return 100.0;
        }

        return round(100.0 * $covered / $statements, 2);
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
