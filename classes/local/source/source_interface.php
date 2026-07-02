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
use local_wb_dashboard\local\dto\filter_constraint;

/**
 * Contract every chart data source implements.
 *
 * A source turns "source params + filter constraints" into a normalized
 * chart_data DTO, applying filters in whatever way is native to it (report
 * filters, wb_table filter API, parameterized WHERE, ...).
 *
 * @package    local_wb_dashboard
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
interface source_interface {
    /**
     * The machine name used in the shortcode (source="...").
     *
     * @return string
     */
    public static function get_name(): string;

    /**
     * Names of the params this source needs. Used to allowlist incoming params.
     *
     * @return string[]
     */
    public function required_params(): array;

    /**
     * Logical filter keys this source can map to its native filtering, given
     * the current params. Keys not listed here are ignored by this source.
     *
     * @param array $sourceparams
     * @return string[]
     */
    public function get_supported_filter_keys(array $sourceparams): array;

    /**
     * Throw if the current user may not view the data these params describe.
     * Called before fetch(); this is the real object-level authorization.
     *
     * @param array $sourceparams
     * @return void
     */
    public function require_access(array $sourceparams): void;

    /**
     * Produce the normalized data, applying the recognized constraints natively.
     *
     * @param array $sourceparams Already allowlisted.
     * @param filter_constraint[] $constraints Already validated.
     * @return chart_data
     */
    public function fetch(array $sourceparams, array $constraints): chart_data;
}
