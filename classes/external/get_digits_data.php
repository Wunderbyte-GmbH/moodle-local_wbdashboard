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
use local_wb_dashboard\local\digits\digits_reducer;
use local_wb_dashboard\local\source\pipeline;
use moodle_exception;

/**
 * Return a single, reduced numeric value (number, count or percentage) for a
 * digits-field definition, ready to write straight into the DOM.
 *
 * Unlike the chart web service (a free-form JSON blob) the shape here is fixed,
 * so it uses strict external typing. Strings are formatted server-side; the
 * client must still set text via textContent, never innerHTML.
 *
 * @package    local_wb_dashboard
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_digits_data extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'source' => new external_value(PARAM_ALPHANUMEXT, 'Source name'),
            'display' => new external_value(PARAM_ALPHA, 'Display mode: number|count|percent', VALUE_DEFAULT, 'number'),
            'sourceparams' => new external_multiple_structure(
                new external_single_structure([
                    'name' => new external_value(PARAM_ALPHANUMEXT, 'Param name'),
                    'value' => new external_value(PARAM_RAW, 'Param value'),
                ]),
                'Source-specific parameters',
                VALUE_DEFAULT,
                []
            ),
            'filtervalues' => new external_multiple_structure(
                new external_single_structure([
                    'key' => new external_value(PARAM_ALPHANUMEXT, 'Logical filter key'),
                    'type' => new external_value(PARAM_ALPHA, 'Filter type'),
                    'value' => new external_value(PARAM_RAW, 'Submitted value'),
                ]),
                'Current page filter values',
                VALUE_DEFAULT,
                []
            ),
            'label' => new external_value(PARAM_TEXT, 'Field label override', VALUE_DEFAULT, ''),
            'decimals' => new external_value(PARAM_INT, 'Decimal places (0-6)', VALUE_DEFAULT, 0),
            'unit' => new external_value(PARAM_TEXT, 'Unit suffix (percent mode always uses %)', VALUE_DEFAULT, ''),
        ]);
    }

    /**
     * Execute.
     *
     * @param string $source
     * @param string $display
     * @param array $sourceparams
     * @param array $filtervalues
     * @param string $label
     * @param int $decimals
     * @param string $unit
     * @return array
     */
    public static function execute(
        string $source,
        string $display = 'number',
        array $sourceparams = [],
        array $filtervalues = [],
        string $label = '',
        int $decimals = 0,
        string $unit = ''
    ): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'source' => $source,
            'display' => $display,
            'sourceparams' => $sourceparams,
            'filtervalues' => $filtervalues,
            'label' => $label,
            'decimals' => $decimals,
            'unit' => $unit,
        ]);

        require_login();
        self::validate_context(context_system::instance());

        if (!digits_reducer::is_valid_mode($params['display'])) {
            throw new moodle_exception('error:unknowndisplaymode', 'local_wb_dashboard', '', $params['display']);
        }

        // Same server pipeline as charts, then collapse the DTO to one value.
        $dto = pipeline::fetch($params['source'], $params['sourceparams'], $params['filtervalues']);
        $result = digits_reducer::reduce($dto, $params['display']);

        $decimals = max(0, min(6, $params['decimals']));
        $displaylabel = $params['label'] !== '' ? $params['label'] : $result->label;

        if ($result->ispercent) {
            $formatted = format_float($result->value, $decimals) . '%';
        } else {
            $formatted = format_float($result->value, $decimals);
            if ($params['unit'] !== '') {
                $formatted .= ' ' . $params['unit'];
            }
        }

        return [
            'value' => $result->value,
            'formatted' => $formatted,
            'ispercent' => $result->ispercent,
            'label' => $displaylabel,
        ];
    }

    /**
     * Returns.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'value' => new external_value(PARAM_FLOAT, 'Raw numeric value'),
            'formatted' => new external_value(PARAM_TEXT, 'Locale-formatted display string'),
            'ispercent' => new external_value(PARAM_BOOL, 'Whether the value is a percentage'),
            'label' => new external_value(PARAM_TEXT, 'Display label'),
        ]);
    }
}
