<?php

use App\Models\Lead;

/**
 * The operator export (decision A-6): confirmed subscribers only, with their
 * consent record.
 */
it('exports the confirmed subscribers as CSV to a file', function () {
    Lead::factory()->create(['email' => 'pending@example.com']);
    Lead::factory()->create([
        'email' => 'nuss@example.com',
        'name' => 'Nuss, Zopf',
        'source' => Lead::SOURCE_PROFILE,
        'consent_version' => '3',
        'requested_at' => '2026-09-01 10:00:00',
        'confirmed_at' => '2026-09-02 11:00:00',
    ]);

    $path = tempnam(sys_get_temp_dir(), 'nz-export');
    $this->artisan('newsletter:export', ['--output' => $path])->assertSuccessful();

    $rows = array_map(str_getcsv(...), file($path, FILE_IGNORE_NEW_LINES));
    unlink($path);

    expect($rows)->toHaveCount(2)
        ->and($rows[0])->toBe(['email', 'name', 'confirmed_at', 'requested_at', 'source', 'consent_version'])
        ->and($rows[1][0])->toBe('nuss@example.com')
        ->and($rows[1][1])->toBe('Nuss, Zopf')
        ->and($rows[1][2])->toStartWith('2026-09-02T11:00:00')
        ->and($rows[1][4])->toBe('profile')
        ->and($rows[1][5])->toBe('3');
});

it('writes to standard output by default', function () {
    Lead::factory()->confirmed()->create(['email' => 'nuss@example.com']);

    ob_start();
    $this->artisan('newsletter:export')->assertSuccessful()->run();
    $output = (string) ob_get_clean();

    expect($output)->toContain('email,name,confirmed_at')->toContain('nuss@example.com');
});
