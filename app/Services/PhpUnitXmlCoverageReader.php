<?php

declare(strict_types=1);

namespace App\Services;

use DOMDocument;
use DOMXPath;

/**
 * Reads PHPUnit XML coverage (--coverage-xml=<dir>): per-file coverage % and which tests cover each file.
 */
class PhpUnitXmlCoverageReader
{
    private const NS = 'https://schema.phpunit.de/coverage/1.0';

    /**
     * Read PHPUnit XML coverage directory. Returns per-file coverage and tests covering each file.
     *
     * @return array{
     *     coverage: array<string, float>,
     *     testsByFile: array<string, list<string>>,
     *     lineCoverage: array<string, array<int, list<string>>>,
     *     totalExecutable: array<string, int>,
     *     globalPercent: float|null
     * }
     */
    public function read(string $dirPath, ?string $projectRoot = null): array
    {
        $coverage = [];
        $testsByFile = [];
        $lineCoverage = [];
        $totalExecutable = [];
        $globalPercent = null;

        $empty = ['coverage' => [], 'testsByFile' => [], 'lineCoverage' => [], 'totalExecutable' => [], 'globalPercent' => null];

        $indexPath = rtrim($dirPath, '/\\') . '/index.xml';
        if (! is_file($indexPath)) {
            return $empty;
        }

        $indexDoc = new DOMDocument();
        if (! @$indexDoc->load($indexPath)) {
            return $empty;
        }

        $indexXpath = new DOMXPath($indexDoc);
        $indexXpath->registerNamespace('p', self::NS);

        $sourcePrefix = $this->readProjectSourcePrefix($indexXpath);
        if ($sourcePrefix === null) {
            $sourcePrefix = '';
        }

        $rootLines = $indexXpath->query("//p:directory[@name='/']/p:totals/p:lines");
        if ($rootLines->length === 0) {
            $rootLines = $indexXpath->query("//p:directory[@name='/']/p:lines");
        }
        if ($rootLines->length === 0) {
            $rootLines = $indexXpath->query("//*[local-name()='directory'][@name='/']/*[local-name()='totals']/*[local-name()='lines']");
        }
        if ($rootLines->length === 0) {
            $rootLines = $indexXpath->query("//*[local-name()='directory'][@name='/']/*[local-name()='lines']");
        }
        if ($rootLines->length > 0 && $rootLines->item(0) !== null) {
            $percent = $rootLines->item(0)->getAttribute('percent');
            if ($percent !== '') {
                $globalPercent = (float) $percent;
            }
        }

        $fileNodes = $indexXpath->query('//p:file[@href]');
        if ($fileNodes->length === 0) {
            $fileNodes = $indexXpath->query("//*[local-name()='file'][@href]");
        }
        $dir = rtrim($dirPath, '/\\') . '/';

        foreach ($fileNodes as $fileNode) {
            $href = $fileNode->getAttribute('href');
            if ($href === '') {
                continue;
            }
            $filePath = $dir . ltrim($href, '/');
            if (! is_file($filePath)) {
                continue;
            }

            $fileDoc = new DOMDocument();
            if (! @$fileDoc->load($filePath)) {
                continue;
            }

            $fileXpath = new DOMXPath($fileDoc);
            $fileXpath->registerNamespace('p', self::NS);

            $fileEl = $fileXpath->query('//p:file')->item(0) ?? $fileXpath->query("//*[local-name()='file']")->item(0);
            if ($fileEl === null) {
                continue;
            }

            $path = $fileEl->getAttribute('path');
            $name = $fileEl->getAttribute('name');
            $pathPart = $path !== '' ? ltrim(str_replace('\\', '/', $path), '/') : '';
            $relativeFromSource = $pathPart !== '' ? $pathPart . '/' . $name : $name;
            $relativePath = $sourcePrefix !== '' ? $sourcePrefix . '/' . $relativeFromSource : $relativeFromSource;

            $linesEl = $fileXpath->query('//p:totals/p:lines')->item(0)
                ?? $fileXpath->query("//*[local-name()='totals']/*[local-name()='lines']")->item(0);
            $percent = 0.0;
            $executable = 0;
            if ($linesEl !== null) {
                $p = $linesEl->getAttribute('percent');
                if ($p !== '') {
                    $percent = (float) $p;
                }
                $ex = $linesEl->getAttribute('executable');
                if ($ex !== '') {
                    $executable = (int) $ex;
                }
            }

            $coveredBy = [];
            $perLine = [];
            $lineNodes = $fileXpath->query('//p:coverage/p:line');
            if ($lineNodes->length === 0) {
                $lineNodes = $fileXpath->query("//*[local-name()='coverage']/*[local-name()='line']");
            }
            foreach ($lineNodes as $lineNode) {
                $nr = $lineNode->getAttribute('nr');
                if ($nr === '') {
                    continue;
                }
                $lineNr = (int) $nr;
                $coveredNodes = $fileXpath->query('p:covered[@by]', $lineNode);
                if ($coveredNodes->length === 0) {
                    $coveredNodes = $fileXpath->query(".//*[local-name()='covered' and @by]", $lineNode);
                }
                $lineTests = [];
                foreach ($coveredNodes as $covered) {
                    $by = $covered->getAttribute('by');
                    if ($by !== '') {
                        $lineTests[] = $by;
                        if (! in_array($by, $coveredBy, true)) {
                            $coveredBy[] = $by;
                        }
                    }
                }
                if ($lineTests !== []) {
                    $perLine[$lineNr] = $lineTests;
                }
            }

            $coverage[$relativePath] = $percent;
            $testsByFile[$relativePath] = $coveredBy;
            $lineCoverage[$relativePath] = $perLine;
            $totalExecutable[$relativePath] = $executable;

            if ($projectRoot !== null) {
                $normalized = $this->normalizePath($projectRoot . '/' . $relativePath);
                $coverage[$normalized] = $percent;
                $testsByFile[$normalized] = $coveredBy;
                $lineCoverage[$normalized] = $perLine;
                $totalExecutable[$normalized] = $executable;
            }
        }

        return [
            'coverage' => $coverage,
            'testsByFile' => $testsByFile,
            'lineCoverage' => $lineCoverage,
            'totalExecutable' => $totalExecutable,
            'globalPercent' => $globalPercent,
        ];
    }

    /**
     * Read project source path from index.xml and return its basename (e.g. "app").
     * Coverage is collected relative to that path; parity expects paths from project root.
     */
    private function readProjectSourcePrefix(DOMXPath $indexXpath): ?string
    {
        $project = $indexXpath->query('//p:project[@source]')->item(0)
            ?? $indexXpath->query("//*[local-name()='project'][@source]")->item(0);
        if ($project === null) {
            return null;
        }
        $source = $project->getAttribute('source');
        if ($source === '') {
            return null;
        }
        $source = str_replace('\\', '/', $source);
        $base = basename($source);

        return $base !== '' ? $base : null;
    }

    private function normalizePath(string $path): string
    {
        $path = str_replace('\\', '/', $path);
        $real = realpath($path);
        return $real !== false ? $real : $path;
    }
}
