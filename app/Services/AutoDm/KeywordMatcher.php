<?php

namespace App\Services\AutoDm;

use App\Models\AutodmAutomationVersion;

/**
 * Does this comment trip this automation?
 *
 * Matching is case-insensitive and accent-insensitive, because people do not
 * type their own language carefully in comments and a trigger that only fires
 * on the exact casing the owner used is a trigger that mostly does not fire.
 *
 * Whole-word matching is offered separately: "art" should be able to avoid
 * firing on "start" and "party" when the owner asks for that, but should not
 * do so silently when they did not.
 */
final class KeywordMatcher
{
    public function matches(AutodmAutomationVersion $version, string $comment): bool
    {
        if ($version->trigger_type === AutodmAutomationVersion::ANY_COMMENT) {
            return true;
        }

        $keywords = $version->keywordList();

        if ($keywords === []) {
            // A keyword automation with no keywords matches nothing. Treating
            // it as "match everything" would turn an unfinished automation into
            // a reply to every comment on the account.
            return false;
        }

        $haystack = $this->fold($comment);

        if ($haystack === '') {
            return false;
        }

        foreach ($keywords as $keyword) {
            $needle = $this->fold($keyword);

            if ($needle === '') {
                continue;
            }

            if ($version->whole_word) {
                // Phrases are supported, so the boundary is applied around the
                // whole phrase rather than around each word in it.
                if (preg_match('/(?<![\p{L}\p{N}])'.preg_quote($needle, '/').'(?![\p{L}\p{N}])/u', $haystack) === 1) {
                    return true;
                }

                continue;
            }

            if (str_contains($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Lowercased, accents stripped, whitespace collapsed.
     *
     * "Café" and "cafe" are the same word to the person typing it, and a
     * trigger that disagrees is one the owner will never work out.
     */
    private function fold(string $value): string
    {
        $text = mb_strtolower(trim($value));

        if (class_exists(\Transliterator::class)) {
            $transliterator = \Transliterator::create('NFD; [:Nonspacing Mark:] Remove; NFC');

            if ($transliterator !== null) {
                $text = (string) $transliterator->transliterate($text);
            }
        }

        return (string) preg_replace('/\s+/u', ' ', $text);
    }
}
