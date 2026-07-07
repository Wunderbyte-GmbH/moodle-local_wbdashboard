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
use local_wb_dashboard\local\filter\base_filter;

/**
 * A free-text "contains" filter.
 *
 * @package    local_wb_dashboard
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class text_filter extends base_filter {
    #[\Override]
    public function get_type(): string {
        return 'text';
    }

    #[\Override]
    public function normalize_value($raw) {
        return trim(clean_param((string)$raw, PARAM_TEXT));
    }

    #[\Override]
    public function to_constraint($value): filter_constraint {
        return new filter_constraint($this->key, filter_constraint::OP_CONTAINS, $value);
    }
}
