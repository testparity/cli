<?php

// Specs: S010-FR-006, S010-FR-010, S010-FR-013, S010-FR-018

use Symfony\Component\Yaml\Yaml;

it('keeps every GitHub workflow valid YAML', function () {
    $paths = [
        base_path('.github/workflows/ci.yml'),
        base_path('.github/workflows/release.yml'),
        base_path('.github/workflows/release-smoke.yml'),
    ];

    foreach ($paths as $path) {
        expect(Yaml::parseFile($path))->toBeArray();
    }
});

it('preserves branch validation and attributable Parity result artifacts', function () {
    $workflow = file_get_contents(base_path('.github/workflows/ci.yml'));

    expect($workflow)
        ->toContain("- '**'")
        ->toContain('workflow_dispatch:')
        ->toContain('php parity check --format=json | tee parity-results.json')
        ->toContain("if: \${{ always() && hashFiles('parity-results.json') != '' }}")
        ->toContain('uses: actions/upload-artifact@v4')
        ->toContain('retention-days: 30');
});

it('gates publication on matching metadata and an exact public package smoke test', function () {
    $release = file_get_contents(base_path('.github/workflows/release.yml'));
    $smoke = file_get_contents(base_path('.github/workflows/release-smoke.yml'));

    expect($release)
        ->toContain('bash dev/verify-release-metadata.sh "${GITHUB_REF_NAME}"')
        ->toContain('draft: true')
        ->toContain('needs: build-assets')
        ->toContain('needs:')
        ->toContain('- packagist-smoke')
        ->not->toContain('> VERSION');

    expect($smoke)
        ->toContain('composer global require "testparity/parity:${TARGET_VERSION}"')
        ->toContain('Installed version mismatch')
        ->toContain('parity check --format=json')
        ->toContain('Latest stable predates parity test; check-only smoke completed.')
        ->toContain('parity test --format=json')
        ->toContain('test -f .parity/per-test/index.json');
});
