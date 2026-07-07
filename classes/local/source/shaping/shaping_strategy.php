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

/**
 * One way of shaping a source's data into a chart_data DTO.
 *
 * Shared across all sources: each strategy decides, from the source params alone,
 * whether it applies, and owns the shaping logic itself. It reaches back into the
 * source only through the generic {@see shapable_source} data-access primitives
 * (load_rows / resolve_field / get_dataset_label), so every shapable source
 * supports every strategy. {@see shaper::shape()} walks the strategies in
 * priority order and hands off to the first whose supports() returns true.
 *
 * @package    local_wb_dashboard
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
interface shaping_strategy {
    /**
     * Whether this strategy handles the given source params.
     *
     * @param array $params Source params.
     * @return bool
     */
    public function supports(array $params): bool;

    /**
     * Shape the source's data into a chart_data DTO.
     *
     * @param shapable_source $source Data access into the source.
     * @param array $params Source params.
     * @param filter_constraint[] $constraints
     * @return chart_data
     */
    public function shape(shapable_source $source, array $params, array $constraints): chart_data;
}
