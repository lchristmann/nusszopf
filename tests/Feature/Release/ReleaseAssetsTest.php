<?php

/**
 * P-16, P16-04: the first release candidate could not be installed. `install.sh` asked GitHub for
 * `.env.production.example`, but GitHub strips a leading dot from an uploaded asset's name and served
 * `default.env.production.example`, so the download was a 404. Nothing in CI ever downloaded a release.
 *
 * What is guarded here is the contract between the installer and the release workflow: every file the installer
 * downloads is attached by the workflow, under a name GitHub keeps.
 */
function installerDownloads(): array
{
    preg_match_all('#\$NUSSZOPF_BASE_URL/([^"\s]+)"#', file_get_contents(base_path('scripts/install.sh')), $matches);

    return array_values(array_unique($matches[1]));
}

function releaseAssets(): array
{
    $workflow = file_get_contents(base_path('.github/workflows/release.yml'));
    preg_match('#gh release create.*?\n((?:\s+release-assets/\S+.*\n?)+)#s', $workflow, $block);
    preg_match_all('#release-assets/(\S+)#', $block[1] ?? '', $matches);

    return array_values(array_unique($matches[1]));
}

it('downloads only files the release workflow attaches', function () {
    $downloads = installerDownloads();

    expect($downloads)->toContain('docker-compose.yaml', 'env.production.example')
        ->and(releaseAssets())->toContain(...$downloads);
});

it('uses no asset name that starts with a dot, which GitHub would rename', function () {
    foreach ([...installerDownloads(), ...releaseAssets()] as $name) {
        expect($name)->not->toStartWith('.');
    }
});

it('still writes the template where the operator looks for it', function () {
    // Only the download name changed; the file on the host stays `.env.production.example` (docs/deployment).
    expect(file_get_contents(base_path('scripts/install.sh')))
        ->toContain('"$NUSSZOPF_BASE_URL/env.production.example" -o .env.production.example');
});
