<?php

namespace App\Support;

/**
 * Meilisearch highlights matches by wrapping them in tags around *unescaped*
 * text. The historical hit card fed that straight into
 * `dangerouslySetInnerHTML` (HitCard.js, HitRequestCard.js), which lets stored
 * markup in a project's own text run in every visitor's browser. Here the
 * search asks for two private-use marker characters instead, and this class
 * escapes the text and turns exactly those markers into `<em>` — the tag the
 * historical index highlighted with (Meilisearch's default).
 */
final class SearchHighlight
{
    public const OPEN = "\u{E000}";

    public const CLOSE = "\u{E001}";

    /** The historical `truncate(..., { length: 90 })` of the card's summary lines. */
    public const SUMMARY_LENGTH = 90;

    /**
     * Escaped HTML of a marked-up string: everything is text except the two markers, which become `<em>`.
     */
    public static function html(string $marked): string
    {
        return str_replace([self::OPEN, self::CLOSE], ['<em>', '</em>'], e($marked));
    }

    /**
     * The plain text of a marked-up string, for stripping the highlight and for its length.
     */
    public static function plain(string $marked): string
    {
        return str_replace([self::OPEN, self::CLOSE], '', $marked);
    }

    /**
     * lodash `truncate`: at most `$length` visible characters, ending in "..." when cut. Markers do not count
     * as characters, and a highlight the cut falls inside is closed.
     */
    public static function truncate(string $marked, int $length = self::SUMMARY_LENGTH): string
    {
        if (mb_strlen(self::plain($marked)) <= $length) {
            return $marked;
        }

        $keep = $length - 3;
        $result = '';
        $visible = 0;
        $open = false;

        foreach (mb_str_split($marked) as $char) {
            if ($char === self::OPEN || $char === self::CLOSE) {
                $open = $char === self::OPEN;
                $result .= $char;

                continue;
            }

            if ($visible === $keep) {
                break;
            }

            $result .= $char;
            $visible++;
        }

        return $result.($open ? self::CLOSE : '').'...';
    }
}
