<?php

namespace Tebex\Util;

class StringUtil {
    /**
     * Checks if a string contains the provided substring.
     *
     * @param string $search        The string to search in.
     * @param string $substring     The substring to search for.
     * @return bool                 True if substring is within search
     */
    public static function containsString(string $search, string $substring): bool
    {
        if ($search === '') { // empty search term
            return false;
        }

        return strpos($search, $substring) !== false;
    }
}
