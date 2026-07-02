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

/**
 * The reduced result of a digits field: a single value plus its display hints.
 *
 * @package    local_wb_dashboard
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class digits_result {
    /** @var float The primary numeric value to display (a count/number, or the percentage itself). */
    public float $value;

    /** @var bool Whether $value is a percentage (0-100). */
    public bool $ispercent;

    /** @var string A fallback label derived from the data (author label overrides this). */
    public string $label;

    /**
     * Constructor.
     *
     * @param float $value
     * @param bool $ispercent
     * @param string $label
     */
    public function __construct(float $value, bool $ispercent, string $label) {
        $this->value = $value;
        $this->ispercent = $ispercent;
        $this->label = $label;
    }
}
