<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace local_wb_dashboard\local\filter;

/**
 * Per-user page filter state persisted in MUC.
 *
 * URL query params are the canonical state (the JS filterbus owns them). This
 * cache is the fallback used to prepopulate controls on a fresh visit with no
 * URL state, and is written whenever a filter changes.
 *
 * @package    local_wb_dashboard
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class page_filter_state {
    /** Maximum number of filter keys stored per page (the WS input is user-controlled). */
    private const MAX_KEYS = 50;

    /** Maximum stored length of a single filter value. */
    private const MAX_VALUE_LENGTH = 1333;

    /**
     * Build the cache key for a user + page.
     *
     * @param int $userid
     * @param string $pageid
     * @return string
     */
    private static function cache_key(int $userid, string $pageid): string {
        return $userid . '_' . clean_param($pageid, PARAM_ALPHANUMEXT);
    }

    /**
     * Get the MUC cache.
     *
     * @return \cache
     */
    private static function cache(): \cache {
        return \cache::make('local_wb_dashboard', 'pagefilterstate');
    }

    /**
     * Return the stored key => value map for a page (current user).
     *
     * @param string $pageid
     * @return array<string, string>
     */
    public static function get(string $pageid): array {
        global $USER;
        $stored = self::cache()->get(self::cache_key((int)$USER->id, $pageid));
        return is_array($stored) ? $stored : [];
    }

    /**
     * Return a single stored value for a page filter key, or a default.
     *
     * @param string $pageid
     * @param string $key
     * @param string $default
     * @return string
     */
    public static function get_value(string $pageid, string $key, string $default = ''): string {
        $state = self::get($pageid);
        return array_key_exists($key, $state) ? (string)$state[$key] : $default;
    }

    /**
     * Persist the key => value map for a page (current user).
     *
     * Keys are cleaned to alphanumext; values are stored as raw strings. The
     * input comes straight from a web service any logged-in user can call, so
     * the number of keys and the value length are capped.
     *
     * @param string $pageid
     * @param array $values
     * @return void
     */
    public static function set(string $pageid, array $values): void {
        global $USER;
        $clean = [];
        foreach ($values as $key => $value) {
            if (count($clean) >= self::MAX_KEYS) {
                break;
            }
            $key = clean_param((string)$key, PARAM_ALPHANUMEXT);
            if ($key !== '') {
                $clean[$key] = \core_text::substr((string)$value, 0, self::MAX_VALUE_LENGTH);
            }
        }
        self::cache()->set(self::cache_key((int)$USER->id, $pageid), $clean);
    }
}
