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

namespace local_wb_dashboard\local\dto;

/**
 * A single normalized data series (one dataset in chart terms).
 *
 * Source-agnostic: sources populate it, the chart builder consumes it.
 *
 * @package    local_wb_dashboard
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class chart_series {
    /** @var string Human-readable series label. */
    public string $label;

    /** @var float[] The numeric values, aligned to chart_data::$labels. */
    public array $data;

    /**
     * @var string[] Colours. One colour per point (pie/doughnut) or a single
     * element used as the whole-series colour (bar/line). Empty = builder fills
     * from the default palette.
     */
    public array $colors;

    /** @var string|null Optional per-series type override for future mixed charts. */
    public ?string $type;

    /** @var string|null Optional grouped-stack id (stacked-bar groups). */
    public ?string $stack;

    /**
     * Constructor.
     *
     * @param string $label
     * @param float[] $data
     * @param string[] $colors
     * @param string|null $type
     * @param string|null $stack
     */
    public function __construct(
        string $label,
        array $data,
        array $colors = [],
        ?string $type = null,
        ?string $stack = null
    ) {
        $this->label = $label;
        $this->data = array_map('floatval', array_values($data));
        $this->colors = array_values($colors);
        $this->type = $type;
        $this->stack = $stack;
    }
}
