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

namespace local_wb_dashboard\local\chart;

/**
 * The supported semantic chart types and their validation.
 *
 * @package    local_wb_dashboard
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class chart_type {
    /** @var string Two-value (or N-slice) doughnut. */
    public const DOUGHNUT = 'doughnut';
    /** @var string Vertical bar. */
    public const BAR = 'bar';
    /** @var string Horizontal bar. */
    public const HORIZONTALBAR = 'horizontalbar';
    /** @var string Grouped stacked bar. */
    public const STACKEDBAR = 'stackedbar';
    /** @var string Horizontal stacked bar with a fixed axis max (fakes a progress bar). */
    public const PROGRESS = 'progress';

    /**
     * All supported semantic types.
     *
     * @return string[]
     */
    public static function all(): array {
        return [
            self::DOUGHNUT,
            self::BAR,
            self::HORIZONTALBAR,
            self::STACKEDBAR,
            self::PROGRESS,
        ];
    }

    /**
     * Whether the given type is supported.
     *
     * @param string $type
     * @return bool
     */
    public static function is_valid(string $type): bool {
        return in_array($type, self::all(), true);
    }
}
