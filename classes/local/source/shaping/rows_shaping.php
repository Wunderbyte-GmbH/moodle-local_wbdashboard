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

namespace local_wb_dashboard\local\source\shaping;

use local_wb_dashboard\local\dto\chart_data;
use local_wb_dashboard\local\dto\chart_series;
use local_wb_dashboard\local\dto\filter_constraint;
use local_wb_dashboard\local\source\shapable_source;
use moodle_exception;

/**
 * Rows: one data point per category, optionally grouped into stacks.
 *
 * Suited to bar/stacked/horizontal charts.
 *
 * @package    local_wb_dashboard
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class rows_shaping implements shaping_strategy {
    /**
     * Applies when a single dataset id and a category field are given.
     *
     * @param array $params Source params.
     * @return bool
     */
    #[\Override]
    public function supports(array $params): bool {
        // The "count" aggregation needs only a category; "sum" also needs a value field.
        $iscount = strtolower((string)($params['aggregation'] ?? '')) === 'count';
        return !empty($params['report']) && !empty($params['categoryfield'])
            && (!empty($params['valuefield']) || $iscount);
    }

    /**
     * One point per category, optionally one series per distinct stack value.
     *
     * @param shapable_source $source Data access into the source.
     * @param array $params Source params.
     * @param filter_constraint[] $constraints
     * @return chart_data
     */
    #[\Override]
    public function shape(shapable_source $source, array $params, array $constraints): chart_data {
        $datasetid = (int)$params['report'];
        $categoryfield = (string)$params['categoryfield'];
        $valuefield = isset($params['valuefield']) ? (string)$params['valuefield'] : '';
        $stackfield = isset($params['stackfield']) ? (string)$params['stackfield'] : '';
        // The "count" option tallies one per row; "sum" (default) adds the value field.
        $iscount = strtolower((string)($params['aggregation'] ?? 'sum')) === 'count';

        $rows = $source->load_rows($datasetid, $constraints);
        if (empty($rows)) {
            throw new moodle_exception('error:noreportdata', 'local_wb_dashboard');
        }

        $catkey = $source->resolve_field($datasetid, $categoryfield, $rows[0]);
        $valkey = (!$iscount && $valuefield !== '')
            ? $source->resolve_field($datasetid, $valuefield, $rows[0]) : '';
        $stackkey = $stackfield !== '' ? $source->resolve_field($datasetid, $stackfield, $rows[0]) : '';

        // Each row contributes 1 (count) or its numeric value field (sum).
        $contribution = function (array $row) use ($iscount, $valkey): float {
            return $iscount ? 1.0 : shaper::to_float($row[$valkey] ?? 0);
        };
        $serieslabel = $iscount
            ? get_string('label:count', 'local_wb_dashboard')
            : format_string($valuefield);

        // Preserve first-seen category order.
        $categories = [];
        foreach ($rows as $row) {
            $cat = (string)($row[$catkey] ?? '');
            $categories[$cat] = true;
        }
        $categories = array_keys($categories);
        $catindex = array_flip($categories);

        $data = new chart_data();
        $data->set_labels(array_map('format_string', $categories));

        if ($stackkey === '') {
            // Single series.
            $values = array_fill(0, count($categories), 0.0);
            foreach ($rows as $row) {
                $cat = (string)($row[$catkey] ?? '');
                $values[$catindex[$cat]] += $contribution($row);
            }
            $data->add_series(new chart_series($serieslabel, $values));
        } else {
            // One series per distinct stack value.
            $stacks = [];
            foreach ($rows as $row) {
                $stack = (string)($row[$stackkey] ?? '');
                if (!isset($stacks[$stack])) {
                    $stacks[$stack] = array_fill(0, count($categories), 0.0);
                }
                $cat = (string)($row[$catkey] ?? '');
                $stacks[$stack][$catindex[$cat]] += $contribution($row);
            }
            foreach ($stacks as $stacklabel => $values) {
                $data->add_series(new chart_series(format_string($stacklabel), $values, [], null, 'group'));
            }
            $data->set_meta('stacked', true);
        }

        return $data;
    }
}
