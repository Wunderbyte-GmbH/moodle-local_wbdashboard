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
use local_wb_dashboard\local\dto\filter_constraint;
use local_wb_dashboard\local\source\shapable_source;
use moodle_exception;

/**
 * Entry point into the shared shaping strategies.
 *
 * Walks the strategies in priority order and hands off to the first whose
 * supports() matches the params, replacing an if/if/if chain in every source:
 * a source's fetch() is just "return shaper::shape($this, $params, $constraints)".
 * New shaping modes are added to STRATEGIES, not as a branch in each source.
 *
 * Also hosts the small value-parsing helpers the strategies share.
 *
 * @package    local_wb_dashboard
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class shaper {
    /**
     * Shaping strategies in priority order: the first whose supports() matches wins.
     *
     * @var class-string<shaping_strategy>[]
     */
    private const STRATEGIES = [
        report_totals_shaping::class,
        two_report_delta_shaping::class,
        rows_shaping::class,
    ];

    /**
     * Shape a source's data with the first strategy that supports the params.
     *
     * @param shapable_source $source The source providing data access.
     * @param array $params Source params.
     * @param filter_constraint[] $constraints
     * @return chart_data
     * @throws moodle_exception If no strategy supports the params.
     */
    public static function shape(shapable_source $source, array $params, array $constraints): chart_data {
        foreach (self::STRATEGIES as $strategyclass) {
            $strategy = new $strategyclass();
            if ($strategy->supports($params)) {
                return $strategy->shape($source, $params, $constraints);
            }
        }
        throw new moodle_exception('error:invalidreportid', 'local_wb_dashboard');
    }

    /**
     * Parse a comma-separated list of dataset ids.
     *
     * @param string $raw
     * @return int[]
     */
    public static function parse_id_list(string $raw): array {
        $ids = [];
        foreach (explode(',', $raw) as $part) {
            $id = (int)trim($part);
            if ($id > 0) {
                $ids[$id] = $id;
            }
        }
        return array_values($ids);
    }

    /**
     * Coerce a formatted cell value to float.
     *
     * @param mixed $value
     * @return float
     */
    public static function to_float($value): float {
        if (is_numeric($value)) {
            return (float)$value;
        }
        // Strip common formatting (thousands separators, stray markup) then retry.
        $clean = preg_replace('/[^0-9,.\-]/', '', (string)$value);
        $clean = str_replace(',', '', $clean);
        return is_numeric($clean) ? (float)$clean : 0.0;
    }
}
