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
 * Multi-dataset totals: one data point per dataset (row count or a summed field).
 *
 * Labels are the dataset names, suited to comparing datasets side by side.
 *
 * @package    local_wb_dashboard
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class report_totals_shaping implements shaping_strategy {
    /**
     * Applies when a list of dataset ids is given.
     *
     * @param array $params Source params.
     * @return bool
     */
    #[\Override]
    public function supports(array $params): bool {
        return !empty($params['reports']);
    }

    /**
     * One data point per dataset: its row count, or the sum of a value field.
     *
     * @param shapable_source $source Data access into the source.
     * @param array $params Source params.
     * @param filter_constraint[] $constraints
     * @return chart_data
     */
    #[\Override]
    public function shape(shapable_source $source, array $params, array $constraints): chart_data {
        $datasetids = shaper::parse_id_list((string)$params['reports']);
        if (empty($datasetids)) {
            throw new moodle_exception('error:invalidreportid', 'local_wb_dashboard');
        }
        $valuefield = isset($params['valuefield']) ? (string)$params['valuefield'] : '';
        $iscount = $valuefield === '' || strtolower((string)($params['aggregation'] ?? 'count')) === 'count';

        $labels = [];
        $values = [];
        foreach ($datasetids as $datasetid) {
            $rows = $source->load_rows($datasetid, $constraints);
            if ($iscount) {
                $values[] = (float)count($rows);
            } else {
                $sum = 0.0;
                $valkey = !empty($rows) ? $source->resolve_field($datasetid, $valuefield, $rows[0]) : '';
                foreach ($rows as $row) {
                    $sum += shaper::to_float($row[$valkey] ?? 0);
                }
                $values[] = $sum;
            }
            $labels[] = $source->get_dataset_label($datasetid);
        }

        $data = new chart_data();
        $data->set_labels($labels);
        $data->add_series(new chart_series(get_string('label:count', 'local_wb_dashboard'), $values));
        return $data;
    }
}
