<?php

namespace App\Support;

class InputSanitizer
{
    /**
     * Strip <script>, <style>, <iframe> container blocks and all HTML tags, then trim.
     * Preserves &, quotes, and % without HTML entity encoding. Returns null if empty.
     */
    public static function clean(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $cleaned = preg_replace('/<(script|style|iframe)\b[^>]*>(.*?)<\/\1>/is', '', $value);
        $cleaned = strip_tags(is_string($cleaned) ? $cleaned : $value);
        $cleaned = trim($cleaned);

        return $cleaned !== '' ? $cleaned : null;
    }
}
