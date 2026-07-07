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

use local_wb_dashboard\local\dto\filter_constraint;

/**
 * Data-access contract a source implements to get the shared shaping strategies.
 *
 * The shaping strategies (rows, multi-dataset totals, two-dataset delta) hold the
 * actual shaping logic and are source-agnostic: they only need a way to load a
 * dataset's rows, resolve a logical field name to a row key, and label a dataset.
 * A source that implements these three primitives supports every shaping mode;
 * its fetch() simply hands off to {@see shaping\shaper::shape()}.
 *
 * A "dataset" is whatever the source's id params point at: a Report Builder
 * report id, a wb_table id, a stored query, ...
 *
 * @package    local_wb_dashboard
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
interface shapable_source extends source_interface {
    /**
     * Load one dataset's formatted rows with the constraints applied natively.
     *
     * @param int|string $datasetid Source-specific dataset id (e.g. a report id).
     * @param filter_constraint[] $constraints
     * @return array List of rows, each an associative array of key => formatted value.
     */
    public function load_rows(int|string $datasetid, array $constraints): array;

    /**
     * Resolve a logical field name (as used in shortcode params) to the row key.
     *
     * @param int|string $datasetid Source-specific dataset id.
     * @param string $field Logical field name, e.g. "durata".
     * @param array $firstrow A sample row, used to match keys directly.
     * @return string The row key to read values from.
     */
    public function resolve_field(int|string $datasetid, string $field, array $firstrow): string;

    /**
     * Human-readable label for a dataset (used e.g. as chart label in totals mode).
     *
     * @param int|string $datasetid Source-specific dataset id.
     * @return string
     */
    public function get_dataset_label(int|string $datasetid): string;
}
