<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * Specs: S003
 */
final class GitChangedLines
{
    /**
     * @return array{merge_base: string, files: list<array{path: string, previous_path: ?string, status: string, lines: list<int>}>}
     */
    public function read(string $root, string $base): array
    {
        if ($base === '') {
            throw new RuntimeException('Supply a Git base ref with --base.');
        }

        $commit = trim($this->git($root, ['rev-parse', '--verify', '--end-of-options', $base.'^{commit}']));
        $mergeBase = trim($this->git($root, ['merge-base', $commit, 'HEAD']));
        $diff = ['diff', '--relative', '--no-ext-diff', '--no-textconv', '--no-color', '--find-renames', '-l0'];
        $fields = explode("\0", $this->git($root, [...$diff, '--name-status', '-z', $mergeBase, '--']));
        $files = [];

        for ($i = 0; $i < count($fields) - 1;) {
            $status = $fields[$i++][0];
            $path = $fields[$i++];
            $previous = null;
            if ($status === 'R' || $status === 'C') {
                $previous = $path;
                $path = $fields[$i++];
            }

            $paths = $previous === null ? [$path] : [$previous, $path];
            $lines = $status === 'D' ? [] : $this->changedLines(
                $this->git($root, [...$diff, '--text', '--unified=0', '--inter-hunk-context=0', $mergeBase, '--', ...$paths])
            );
            $files[] = ['path' => $path, 'previous_path' => $previous, 'status' => $status, 'lines' => $lines];
        }

        return ['merge_base' => $mergeBase, 'files' => $files];
    }

    /** @return list<int> */
    private function changedLines(string $patch): array
    {
        preg_match_all('/^@@ -[0-9]+(?:,[0-9]+)? \+([0-9]+)(?:,([0-9]+))? @@/m', $patch, $hunks, PREG_SET_ORDER);
        $lines = [];
        foreach ($hunks as $hunk) {
            $first = (int) $hunk[1];
            $count = (int) ($hunk[2] ?? 1);
            for ($line = $first; $line < $first + $count; $line++) {
                $lines[] = $line;
            }
        }

        return $lines;
    }

    /** @param list<string> $arguments */
    private function git(string $root, array $arguments): string
    {
        $process = new Process(['git', '--literal-pathspecs', ...$arguments], $root);
        $process->run();
        if (! $process->isSuccessful()) {
            throw new RuntimeException('Git '.$arguments[0].' failed. '.trim($process->getErrorOutput()));
        }

        return $process->getOutput();
    }
}
