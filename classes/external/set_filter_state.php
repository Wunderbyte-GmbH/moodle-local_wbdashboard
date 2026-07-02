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

namespace local_wb_dashboard\external;

use context_system;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use local_wb_dashboard\local\filter\page_filter_state;

/**
 * Persist the current user's page filter state (fallback for URL state).
 *
 * @package    local_wb_dashboard
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class set_filter_state extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'pageid' => new external_value(PARAM_ALPHANUMEXT, 'Page identifier'),
            'filtervalues' => new external_multiple_structure(
                new external_single_structure([
                    'key' => new external_value(PARAM_ALPHANUMEXT, 'Logical filter key'),
                    'value' => new external_value(PARAM_RAW, 'Submitted value'),
                ]),
                'Filter values to persist',
                VALUE_DEFAULT,
                []
            ),
        ]);
    }

    /**
     * Execute.
     *
     * @param string $pageid
     * @param array $filtervalues
     * @return array
     */
    public static function execute(string $pageid, array $filtervalues = []): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'pageid' => $pageid,
            'filtervalues' => $filtervalues,
        ]);

        require_login();
        self::validate_context(context_system::instance());

        $values = [];
        foreach ($params['filtervalues'] as $fv) {
            $values[$fv['key']] = $fv['value'];
        }
        page_filter_state::set($params['pageid'], $values);

        return ['status' => true];
    }

    /**
     * Returns.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'status' => new external_value(PARAM_BOOL, 'Whether the state was saved'),
        ]);
    }
}
