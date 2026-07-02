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

namespace local_wb_dashboard\local\source;

use local_wb_dashboard\local\dto\chart_data;
use local_wb_dashboard\local\filter\filter_factory;

/**
 * The shared server-side data-acquisition pipeline.
 *
 * Resolves the source, allowlists its params, enforces object-level access,
 * translates the page filter values into neutral constraints and fetches the
 * normalized chart_data DTO. Every display web service (charts, digits, ...)
 * runs this identical pipeline; only what it does with the DTO differs.
 *
 * @package    local_wb_dashboard
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class pipeline {
    /**
     * Resolve a source and fetch its normalized data for the given params/filters.
     *
     * @param string $sourcename Registered source name.
     * @param array $sourceparams WS name/value pair list of source parameters.
     * @param array $filtervalues WS list of {key,type,value} page filter values.
     * @return chart_data
     */
    public static function fetch(string $sourcename, array $sourceparams, array $filtervalues): chart_data {
        // Resolve the source (throws on unknown).
        $source = source_registry::get($sourcename);

        // Allowlist source params against what the source declares it needs.
        $allowed = array_flip($source->required_params());
        $cleanparams = [];
        foreach ($sourceparams as $pair) {
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
        foreach ($filtervalues as $fv) {
            if ($fv['value'] === '' || !isset($supported[$fv['key']])) {
                continue;
            }
            if (!filter_factory::exists($fv['type'])) {
                continue;
            }
            $filter = filter_factory::create($fv['type'], $fv['key']);
            $constraints[] = $filter->to_constraint($filter->normalize_value($fv['value']));
        }

        return $source->fetch($cleanparams, $constraints);
    }
}
