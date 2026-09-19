<?php

use App\Support\ProjectDate;

/**
 * PeriodField.js / parseDateISOString / toLocaleDateString('de-DE'):
 * docs/rewrite/second-slice.md, "Period storage".
 */
it('parses d.m.yyyy with one- or two-digit day and month', function (string $input) {
    expect(ProjectDate::parse($input)?->format('Y-m-d'))->toBe('2027-03-04');
})->with(['04.03.2027', '4.3.2027', '4.03.2027', '04.3.2027', ' 4.3.2027 ']);

it('resolves two-digit years into 1950-2049', function (string $input, string $expected) {
    expect(ProjectDate::parse($input)?->format('Y-m-d'))->toBe($expected);
})->with([
    ['1.1.27', '2027-01-01'],
    ['1.1.49', '2049-01-01'],
    ['1.1.50', '1950-01-01'],
    ['1.1.99', '1999-01-01'],
]);

it('rejects malformed and impossible dates', function (string $input) {
    expect(ProjectDate::parse($input))->toBeNull()
        ->and(ProjectDate::isValid($input))->toBeFalse();
})->with(['', 'morgen', '2027-03-04', '31.2.2027', '29.2.2027', '32.1.2027', '1.13.2027', '1.1.202', '1.1.2', '1/1/2027', '1.1.2027.', '.1.2027']);

it('accepts a leap day only in a leap year', function () {
    expect(ProjectDate::isValid('29.2.2028'))->toBeTrue()
        ->and(ProjectDate::isValid('29.2.2027'))->toBeFalse();
});

it('stores an ISO-8601 date-time at midnight, not the dd.MM.yyyy input string', function () {
    $stored = ProjectDate::toStored('4.3.2027');

    expect($stored)->toMatch('/^2027-03-04T00:00:00[+-]\d{2}:\d{2}$/')
        ->and(ProjectDate::toStored(''))->toBe('')
        ->and(ProjectDate::toStored('kaputt'))->toBe('');
});

it('displays the stored calendar date as j.n.Y regardless of the offset or viewer time zone (BUG-023)', function (string $stored, string $expected) {
    expect(ProjectDate::toDisplay($stored))->toBe($expected);
})->with([
    ['2027-03-04T00:00:00+01:00', '4.3.2027'],
    ['2027-03-04T00:00:00-08:00', '4.3.2027'],
    ['2027-12-31T00:00:00+00:00', '31.12.2027'],
    ['', ''],
    ['nonsense', ''],
]);

it('round-trips form input through storage to the edit display', function () {
    expect(ProjectDate::toDisplay(ProjectDate::toStored('04.03.2027')))->toBe('4.3.2027');
});
