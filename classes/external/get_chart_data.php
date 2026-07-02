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
use local_wb_dashboard\local\chart\chart_director;
use local_wb_dashboard\local\filter\filter_factory;
use local_wb_dashboard\local\source\source_registry;

/**
 * Return the fully-built chart configuration for a chart definition.
 *
 * The return is a JSON string ("payload"): the DTO/config varies by source and
 * type and carries free-form metadata, which does not fit Report Builder's
 * strict external typing. The payload is fully sanitized in PHP before encoding
 * (numbers cast to float, all strings passed through format_string upstream in
 * the source/builder), so PARAM_RAW is safe here — the client must still set any
 * text via textContent, never innerHTML.
 *
 * @package    local_wb_dashboard
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_chart_data extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'source' => new external_value(PARAM_ALPHANUMEXT, 'Source name'),
            'type' => new external_value(PARAM_ALPHA, 'Semantic chart type'),
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
            'colors' => new external_multiple_structure(
                new external_value(PARAM_TEXT, 'CSS colour'),
                'Colour palette override',
                VALUE_DEFAULT,
                []
            ),
            'title' => new external_value(PARAM_TEXT, 'Chart title', VALUE_DEFAULT, ''),
            'centertext' => new external_value(PARAM_BOOL, 'Show doughnut centre text', VALUE_DEFAULT, true),
        ]);
    }

    /**
     * Execute.
     *
     * @param string $source
     * @param string $type
     * @param array $sourceparams
     * @param array $filtervalues
     * @param array $colors
     * @param string $title
     * @return array
     */
    public static function execute(
        string $source,
        string $type,
        array $sourceparams = [],
        array $filtervalues = [],
        array $colors = [],
        string $title = '',
        bool $centertext = true
    ): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'source' => $source,
            'type' => $type,
            'sourceparams' => $sourceparams,
            'filtervalues' => $filtervalues,
            'colors' => $colors,
            'title' => $title,
            'centertext' => $centertext,
        ]);

        require_login();
        self::validate_context(context_system::instance());

        // Resolve the source (throws on unknown).
        $source = source_registry::get($params['source']);

        // Allowlist source params against what the source declares it needs.
        $allowed = array_flip($source->required_params());
        $cleanparams = [];
        foreach ($params['sourceparams'] as $pair) {
            if (isset($allowed[$pair['name']])) {
                $cleanparams[$pair['name']] = $pair['value'];
            }
        }

        // Real object-level authorization lives in the source.
        $source->require_access($cleanparams);

        // Build neutral constraints from the submitted filter values, ignoring
        // keys this source cannot map.
        $supported = array_flip($source->get_supported_filter_keys($cleanparams));
        $constraints = [];
        foreach ($params['filtervalues'] as $fv) {
            if ($fv['value'] === '' || !isset($supported[$fv['key']])) {
                continue;
            }
            if (!filter_factory::exists($fv['type'])) {
                continue;
            }
            $filter = filter_factory::create($fv['type'], $fv['key']);
            $constraints[] = $filter->to_constraint($filter->normalize_value($fv['value']));
        }

        // Fetch normalized data, then build the FULL chart config.
        $dto = $source->fetch($cleanparams, $constraints);
        $config = (new chart_director())->build($params['type'], $dto, [
            'colors' => $params['colors'],
            'title' => $params['title'],
            'centertext' => $params['centertext'],
        ]);

        return ['payload' => json_encode($config->jsonSerialize())];
    }

    /**
     * Returns.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'payload' => new external_value(PARAM_RAW, 'JSON-encoded, sanitized Chart.js config'),
        ]);
    }
}
