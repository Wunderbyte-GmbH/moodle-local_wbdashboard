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

namespace local_wb_dashboard\local\digits;

use local_wb_dashboard\local\dto\chart_data;

/**
 * Reduces a normalized chart_data DTO to a single displayable value.
 *
 * The same DTO the chart builder consumes is collapsed here to one number:
 *  - number / count: the sum of the first series' data points. Meaningful when
 *    the points are parts of one whole (rows-mode count over categories, or a
 *    single report); summing counts of unrelated reports is not.
 *  - percent: base / total * 100. The total is, in order of preference:
 *      1. the DTO's axismax meta (the two-report delta shaping: logged vs total);
 *      2. the second data point (a two-report ratio, e.g. subset vs all — the
 *         first report is the part, the second the whole);
 *      3. the base itself (a single value: 100% or, when zero, 0%).
 *
 * @package    local_wb_dashboard
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class digits_reducer {
    /** A plain number (sum of the series). */
    public const MODE_NUMBER = 'number';

    /** A count (functionally the sum of the series; alias of number for readability). */
    public const MODE_COUNT = 'count';

    /** A percentage derived from a base/total delta. */
    public const MODE_PERCENT = 'percent';

    /**
     * The valid display modes.
     *
     * @return string[]
     */
    public static function modes(): array {
        return [self::MODE_NUMBER, self::MODE_COUNT, self::MODE_PERCENT];
    }

    /**
     * Whether a display mode is valid.
     *
     * @param string $mode
     * @return bool
     */
    public static function is_valid_mode(string $mode): bool {
        return in_array($mode, self::modes(), true);
    }

    /**
     * Reduce the DTO to a single value for the given display mode.
     *
     * @param chart_data $data
     * @param string $mode One of the MODE_* constants.
     * @return digits_result
     */
    public static function reduce(chart_data $data, string $mode): digits_result {
        $series = $data->series[0] ?? null;
        $values = ($series !== null) ? $series->data : [];
        $label = ($series !== null && $series->label !== '') ? $series->label : '';

        if ($mode === self::MODE_PERCENT) {
            $base = $values[0] ?? 0.0;
            if (array_key_exists('axismax', $data->meta)) {
                // Two-report delta shaping: total is the fixed axis maximum.
                $total = (float)$data->meta['axismax'];
            } else if (count($values) >= 2) {
                // Two-report ratio: the first report is the part, the second the
                // whole (e.g. subset of users vs all users).
                $total = $values[1];
            } else {
                // A single value: 100%, or 0% when the base itself is zero.
                $total = $base;
            }
            // Guard against divide-by-zero.
            $percent = ($total > 0) ? ($base / $total) * 100.0 : 0.0;
            return new digits_result($percent, true, $label);
        }

        return new digits_result(array_sum($values), false, $label);
    }
}
