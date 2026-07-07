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

namespace local_wb_dashboard\local\filter;

use local_wb_dashboard\local\dto\filter_constraint;
use renderer_base;

/**
 * Contract for a page-level filter control.
 *
 * A filter owns its UI control + value normalization + neutral constraint. It
 * never builds SQL; the source applies the constraint natively.
 *
 * @package    local_wb_dashboard
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
interface filter_interface {
    /**
     * The logical filter key (shared page vocabulary).
     *
     * @return string
     */
    public function get_key(): string;

    /**
     * The filter type name (matches the factory switch, e.g. "select").
     *
     * @return string
     */
    public function get_type(): string;

    /**
     * Context for rendering the control (label, options, current value, id, key, type).
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output): array;

    /**
     * Validate/coerce a raw submitted value into a clean internal value.
     *
     * @param mixed $raw
     * @return mixed
     */
    public function normalize_value($raw);

    /**
     * Produce the neutral, source-agnostic constraint for a normalized value.
     *
     * @param mixed $value Normalized value (from normalize_value()).
     * @return filter_constraint
     */
    public function to_constraint($value): filter_constraint;
}
