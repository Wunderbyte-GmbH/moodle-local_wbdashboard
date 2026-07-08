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

use context_system;

/**
 * Filter keys whose value is locked to a user profile field.
 *
 * The lockedfilters admin setting maps logical filter keys to user profile
 * field shortnames ("region=region", one per line). For every user without the
 * local/wb_dashboard:ignorelockedfilters capability, a mapped key is locked:
 * the pipeline discards whatever the client submitted for it and forces the
 * user's own profile field value instead, and the [chartfilter] shortcode
 * renders a static value instead of a control.
 *
 * A locked key with an empty profile field value stays in the map with value
 * '': callers must fail closed (no data), never fall back to unfiltered.
 *
 * @package    local_wb_dashboard
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class locked_filters {
    /**
     * Parse the lockedfilters setting into filter key => profile field shortname.
     *
     * @return array<string, string>
     */
    public static function mappings(): array {
        $raw = (string)get_config('local_wb_dashboard', 'lockedfilters');
        $mappings = [];
        foreach (preg_split('/\R+/', $raw) as $line) {
            if (strpos($line, '=') === false) {
                continue;
            }
            [$key, $shortname] = array_map('trim', explode('=', $line, 2));
            $key = clean_param($key, PARAM_ALPHANUMEXT);
            $shortname = clean_param($shortname, PARAM_ALPHANUMEXT);
            if ($key !== '' && $shortname !== '') {
                $mappings[$key] = $shortname;
            }
        }
        return $mappings;
    }

    /**
     * The forced filter values for a user: filter key => profile field value.
     *
     * Empty when nothing is mapped or the user may ignore locked filters. A
     * mapped key whose profile field is empty/missing is returned with value ''.
     *
     * @param int $userid
     * @return array<string, string>
     */
    public static function for_user(int $userid): array {
        global $CFG;

        $mappings = self::mappings();
        if (empty($mappings)) {
            return [];
        }
        if (has_capability('local/wb_dashboard:ignorelockedfilters', context_system::instance(), $userid)) {
            return [];
        }

        require_once($CFG->dirroot . '/user/profile/lib.php');
        $record = profile_user_record($userid, false);

        $locked = [];
        foreach ($mappings as $key => $shortname) {
            $locked[$key] = trim((string)($record->{$shortname} ?? ''));
        }
        return $locked;
    }

    /**
     * The forced filter values for the current user.
     *
     * @return array<string, string>
     */
    public static function for_current_user(): array {
        global $USER;
        return self::for_user((int)$USER->id);
    }
}
