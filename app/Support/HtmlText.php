<?php

namespace App\Support;

class HtmlText
{
    public static function decode(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return $value;
        }

        $decoded = $value;

        // Some catalogue values are encoded twice, for example &amp;quot;.
        for ($attempt = 0; $attempt < 2; $attempt++) {
            $next = html_entity_decode(
                $decoded,
                ENT_QUOTES | ENT_HTML5 | ENT_SUBSTITUTE,
                'UTF-8'
            );

            if ($next === $decoded) {
                break;
            }

            $decoded = $next;
        }

        return str_replace("\u{00A0}", ' ', $decoded);
    }
}
