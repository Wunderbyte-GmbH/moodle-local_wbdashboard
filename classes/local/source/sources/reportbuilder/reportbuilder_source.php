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

namespace local_wb_dashboard\local\source\sources\reportbuilder;

use core_reportbuilder\local\filters\date;
use core_reportbuilder\local\filters\number;
use core_reportbuilder\local\filters\select;
use core_reportbuilder\local\filters\text;
use core_reportbuilder\manager;
use core_reportbuilder\permission;
use local_wb_dashboard\local\dto\chart_data;
use local_wb_dashboard\local\dto\filter_constraint;
use local_wb_dashboard\local\source\shapable_source;
use local_wb_dashboard\local\source\shaping\shaper;

/**
 * Report Builder data source.
 *
 * A dataset is a Report Builder report; this class only provides data access
 * (load rows, resolve field names, label datasets). The shaping itself lives in
 * the shared shaping strategies — see {@see shaper} — so this source supports
 * every shaping mode (multi-report totals, two-report delta, rows) without
 * shaping code of its own.
 *
 * Filters are applied the report-native way: constraints are translated into the
 * report's own filter values (via Report Builder's user_filter_manager), applied
 * around the query and then restored so no state leaks into the report UI.
 *
 * @package    local_wb_dashboard
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class reportbuilder_source implements shapable_source {
    #[\Override]
    public static function get_name(): string {
        return 'reportbuilder';
    }

    #[\Override]
    public function required_params(): array {
        return [
            'idbase', 'fieldbase', 'idtotal', 'fieldtotal',
            'report', 'categoryfield', 'valuefield', 'stackfield', 'aggregation',
            'reports',
        ];
    }

    #[\Override]
    public function get_supported_filter_keys(array $sourceparams): array {
        $keys = [];
        foreach ($this->report_ids($sourceparams) as $reportid) {
            $report = manager::get_report_from_id($reportid);
            foreach ($report->get_active_filters() as $filter) {
                $uid = $filter->get_unique_identifier();
                $keys[$uid] = $uid;
                // Also expose the short name after the entity prefix as a friendly key.
                if (($pos = strpos($uid, ':')) !== false) {
                    $short = substr($uid, $pos + 1);
                    $keys[$short] = $short;
                }
            }
        }
        return array_values($keys);
    }

    #[\Override]
    public function require_access(array $sourceparams): void {
        foreach ($this->report_ids($sourceparams) as $reportid) {
            $report = manager::get_report_from_id($reportid);
            permission::require_can_view_report($report->get_report_persistent());
        }
    }

    #[\Override]
    public function fetch(array $sourceparams, array $constraints): chart_data {
        return shaper::shape($this, $sourceparams, $constraints);
    }

    /**
     * Run a report with the recognized constraints applied as report-native
     * filter values, restoring the user's prior filter state afterwards.
     *
     * @param int|string $datasetid Report id.
     * @param filter_constraint[] $constraints
     * @return array Formatted report rows.
     */
    #[\Override]
    public function load_rows(int|string $datasetid, array $constraints): array {
        $reportid = (int)$datasetid;
        $report = manager::get_report_from_id($reportid);
        $filtervalues = $this->build_filter_values($report, $constraints);

        if (empty($filtervalues)) {
            return (new reporthandler($reportid))->return_data();
        }

        // Apply our values on top of the user's current ones, query, then restore.
        $previous = $report->get_filter_values();
        try {
            $report->set_filter_values(array_merge($previous, $filtervalues));
            return (new reporthandler($reportid))->return_data();
        } finally {
            $report->set_filter_values($previous);
        }
    }

    /**
     * Resolve a logical field name to the report's row key.
     *
     * @param int|string $datasetid Report id.
     * @param string $field Logical field name.
     * @param array $firstrow A sample row.
     * @return string
     */
    #[\Override]
    public function resolve_field(int|string $datasetid, string $field, array $firstrow): string {
        return reporthandler::resolve_field_name((int)$datasetid, $field, $firstrow);
    }

    /**
     * The report's name, formatted for output.
     *
     * @param int|string $datasetid Report id.
     * @return string
     */
    #[\Override]
    public function get_dataset_label(int|string $datasetid): string {
        $report = manager::get_report_from_id((int)$datasetid);
        return format_string($report->get_report_persistent()->get('name'));
    }

    /**
     * The report ids referenced by the given params.
     *
     * @param array $params
     * @return int[]
     */
    private function report_ids(array $params): array {
        $ids = [];
        foreach (['idbase', 'idtotal', 'report'] as $key) {
            if (!empty($params[$key])) {
                $ids[(int)$params[$key]] = (int)$params[$key];
            }
        }
        foreach (shaper::parse_id_list((string)($params['reports'] ?? '')) as $id) {
            $ids[$id] = $id;
        }
        return array_values($ids);
    }

    /**
     * Translate constraints into the report's filter-form value array.
     *
     * @param \core_reportbuilder\datasource $report
     * @param filter_constraint[] $constraints
     * @return array
     */
    private function build_filter_values($report, array $constraints): array {
        if (empty($constraints)) {
            return [];
        }

        // Index the report's active filters by both full identifier and short name.
        $byidentifier = [];
        foreach ($report->get_active_filters() as $filter) {
            $uid = $filter->get_unique_identifier();
            $byidentifier[strtolower($uid)] = $filter;
            if (($pos = strpos($uid, ':')) !== false) {
                $byidentifier[strtolower(substr($uid, $pos + 1))] = $filter;
            }
        }

        $values = [];
        foreach ($constraints as $constraint) {
            $filter = $byidentifier[strtolower($constraint->key)] ?? null;
            if ($filter === null) {
                continue; // This report does not map that key: ignore it.
            }
            $values += $this->constraint_to_filter_values($filter, $constraint);
        }
        return $values;
    }

    /**
     * Build the filter-form values for one constraint, branching on filter type.
     *
     * @param \core_reportbuilder\local\report\filter $filter
     * @param filter_constraint $constraint
     * @return array
     */
    private function constraint_to_filter_values($filter, filter_constraint $constraint): array {
        $name = $filter->get_unique_identifier();
        $class = $filter->get_filter_class();
        $value = $constraint->value;

        if (is_a($class, select::class, true)) {
            return [
                "{$name}_operator" => select::EQUAL_TO,
                "{$name}_value" => $value,
            ];
        }

        if (is_a($class, text::class, true)) {
            $operator = $constraint->operator === filter_constraint::OP_EQUAL ? text::IS_EQUAL_TO : text::CONTAINS;
            return [
                "{$name}_operator" => $operator,
                "{$name}_value" => (string)$value,
            ];
        }

        if (is_a($class, number::class, true)) {
            switch ($constraint->operator) {
                case filter_constraint::OP_GREATER_EQUAL:
                    return ["{$name}_operator" => number::EQUAL_OR_GREATER_THAN, "{$name}_value1" => (float)$value];
                case filter_constraint::OP_LESS_EQUAL:
                    return ["{$name}_operator" => number::EQUAL_OR_LESS_THAN, "{$name}_value1" => (float)$value];
                case filter_constraint::OP_BETWEEN:
                    [$min, $max] = is_array($value) ? array_values($value) : [$value, $value];
                    return [
                        "{$name}_operator" => number::RANGE,
                        "{$name}_value1" => (float)$min,
                        "{$name}_value2" => (float)$max,
                    ];
                default:
                    return ["{$name}_operator" => number::EQUAL_TO, "{$name}_value1" => (float)$value];
            }
        }

        if (is_a($class, date::class, true)) {
            [$from, $to] = is_array($value) ? array_values($value) : [$value, 0];
            return [
                "{$name}_operator" => date::DATE_RANGE,
                "{$name}_from" => (int)$from,
                "{$name}_to" => (int)$to,
            ];
        }

        return [];
    }
}
