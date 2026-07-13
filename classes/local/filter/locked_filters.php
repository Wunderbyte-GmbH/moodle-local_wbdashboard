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
 * field shortnames, one mapping per line ("region=region"). A mapping may be
 * scoped to one or more roles with a "|role1,role2" suffix
 * ("region=region|regionalmanager"): then only users assigned one of those
 * roles (in the system context) get the key locked, so different roles can have
 * different subsets of filters frozen on the same page. A mapping with no role
 * suffix applies to everyone (the historical behaviour).
 *
 * For every affected user, a locked key is forced server-side: the pipeline
 * discards whatever the client submitted for it and applies the user's own
 * profile field value instead, and the [chartfilter] shortcode renders a static
 * value instead of a control. The local/wb_dashboard:ignorelockedfilters
 * capability exempts a user from all locks regardless of role.
 *
 * A locked key with an empty profile field value stays in the map with value
 * '': callers must fail closed (no data), never fall back to unfiltered. An
 * unmatched role name (e.g. a role not created yet) simply means the lock
 * applies to nobody — role shortnames must match existing roles to take effect.
 *
 * @package    local_wb_dashboard
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class locked_filters {
    /**
     * Parse the lockedfilters setting into filter key => {field, roles}.
     *
     * @return array<string, array{field: string, roles: string[]}>
     */
    public static function mappings(): array {
        $raw = (string)get_config('local_wb_dashboard', 'lockedfilters');
        $mappings = [];
        foreach (preg_split('/\R+/', $raw) as $line) {
            if (strpos($line, '=') === false) {
                continue;
            }
            [$key, $rest] = array_map('trim', explode('=', $line, 2));

            // Optional "|role1,role2" suffix scopes the lock to those roles.
            $roles = [];
            if (($pipe = strpos($rest, '|')) !== false) {
                foreach (explode(',', substr($rest, $pipe + 1)) as $role) {
                    $role = clean_param(trim($role), PARAM_ALPHANUMEXT);
                    if ($role !== '') {
                        $roles[$role] = $role;
                    }
                }
                $rest = substr($rest, 0, $pipe);
            }

            $key = clean_param($key, PARAM_ALPHANUMEXT);
            $shortname = clean_param(trim($rest), PARAM_ALPHANUMEXT);
            if ($key !== '' && $shortname !== '') {
                $mappings[$key] = ['field' => $shortname, 'roles' => array_values($roles)];
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
        // Master exemption: this capability ignores every lock, regardless of role.
        if (has_capability('local/wb_dashboard:ignorelockedfilters', context_system::instance(), $userid)) {
            return [];
        }

        require_once($CFG->dirroot . '/user/profile/lib.php');
        $record = profile_user_record($userid, false);

        // The user's system-context role shortnames, resolved once and only when
        // a role-scoped mapping actually needs them.
        $userroles = null;

        $locked = [];
        foreach ($mappings as $key => $mapping) {
            if (!empty($mapping['roles'])) {
                $userroles ??= self::user_role_shortnames($userid);
                if (empty(array_intersect($mapping['roles'], $userroles))) {
                    continue; // Lock is scoped to roles this user is not assigned.
                }
            }
            $locked[$key] = trim((string)($record->{$mapping['field']} ?? ''));
        }
        return $locked;
    }

    /**
     * The shortnames of the roles assigned to the user in the system context.
     *
     * @param int $userid
     * @return string[]
     */
    private static function user_role_shortnames(int $userid): array {
        $shortnames = [];
        foreach (get_user_roles(context_system::instance(), $userid, false) as $role) {
            $shortnames[$role->shortname] = $role->shortname;
        }
        return array_values($shortnames);
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
