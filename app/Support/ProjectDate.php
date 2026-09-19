<?php

namespace App\Support;

use Carbon\CarbonImmutable;

/**
 * The historical project-period date handling.
 *
 * Form/input representation is a German `d.m.yyyy` string (`PeriodField.js`,
 * `dd.MM.yyyy`, with the day/month accepted as one or two digits and a two- or
 * four-digit year); the persisted `period.from`/`period.to` are ISO-8601
 * date-time strings at local midnight (`parseDateISOString` → `formatISO`),
 * NOT `dd.MM.yyyy` — see docs/rewrite/second-slice.md, "Period storage".
 * Display re-renders the *stored calendar date* (`j.n.Y`, what
 * `toLocaleDateString('de-DE')` produced), independent of the viewer's time
 * zone (BUG-023).
 */
final class ProjectDate
{
    private const PATTERN = '/^(\d{1,2})\.(\d{1,2})\.(\d{4}|\d{2})$/';

    /**
     * Parses a `d.m.yyyy`/`d.m.yy` string; a two-digit year lands in
     * 1950–2049 (date-fns' `yy` resolution against its 2000 reference date).
     */
    public static function parse(string $input): ?CarbonImmutable
    {
        if (! preg_match(self::PATTERN, trim($input), $m)) {
            return null;
        }

        [$day, $month, $year] = [(int) $m[1], (int) $m[2], (int) $m[3]];

        if (strlen($m[3]) === 2) {
            $year += $year < 50 ? 2000 : 1900;
        }

        if (! checkdate($month, $day, $year)) {
            return null;
        }

        return CarbonImmutable::create($year, $month, $day, 0, 0, 0, config('app.timezone'));
    }

    public static function isValid(string $input): bool
    {
        return self::parse($input) !== null;
    }

    /**
     * Form string -> stored ISO-8601 string ('' stays '').
     */
    public static function toStored(string $input): string
    {
        return self::parse($input)?->toIso8601String() ?? '';
    }

    /**
     * Stored ISO-8601 string -> `j.n.Y` display/edit string ('' for empty or
     * unparseable), read from the stored calendar date itself.
     */
    public static function toDisplay(?string $stored): string
    {
        if ($stored === null || ! preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $stored, $m)) {
            return '';
        }

        return ((int) $m[3]).'.'.((int) $m[2]).'.'.$m[1];
    }
}
