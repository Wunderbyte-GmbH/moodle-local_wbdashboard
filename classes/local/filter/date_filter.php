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

/**
 * A date filter. The control submits a "YYYY-MM-DD" value meaning "from this date
 * onward"; the normalized value is a unix timestamp. Sources decide how to apply
 * it (Report Builder maps it to a DATE_RANGE from-value).
 *
 * @package    local_wb_dashboard
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class date_filter extends base_filter {
    #[\Override]
    public function get_type(): string {
        return 'date';
    }

    #[\Override]
    public function normalize_value($raw) {
        $raw = trim((string)$raw);
        if ($raw === '') {
            return 0;
        }
        // Accept ISO date "YYYY-MM-DD".
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $raw, $m)) {
            return make_timestamp((int)$m[1], (int)$m[2], (int)$m[3], 0, 0, 0);
        }
        // Accept a raw unix timestamp.
        if (ctype_digit($raw)) {
            return (int)$raw;
        }
        return 0;
    }

    #[\Override]
    public function to_constraint($value): filter_constraint {
        return new filter_constraint($this->key, filter_constraint::OP_GREATER_EQUAL, (int)$value);
    }
}
