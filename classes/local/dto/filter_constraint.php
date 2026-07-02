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
 * A source-agnostic filter constraint: the neutral expression of user intent.
 *
 * A filter produces one of these; each source decides how to apply it natively
 * (report filter, wb_table filter API, parameterized WHERE, ...).
 *
 * @package    local_wb_dashboard
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class filter_constraint {
    /** @var string Equality operator. */
    public const OP_EQUAL = 'eq';
    /** @var string Substring-match operator. */
    public const OP_CONTAINS = 'contains';
    /** @var string Greater-than-or-equal operator. */
    public const OP_GREATER_EQUAL = 'gte';
    /** @var string Less-than-or-equal operator. */
    public const OP_LESS_EQUAL = 'lte';
    /** @var string Range operator (value is [min, max]). */
    public const OP_BETWEEN = 'between';

    /** @var string Logical filter key (shared page vocabulary, e.g. "courseid"). */
    public string $key;

    /** @var string One of the OP_* constants. */
    public string $operator;

    /** @var mixed The submitted, normalized value (scalar or [min, max] for between). */
    public $value;

    /**
     * Constructor.
     *
     * @param string $key
     * @param string $operator
     * @param mixed $value
     */
    public function __construct(string $key, string $operator, $value) {
        $this->key = $key;
        $this->operator = $operator;
        $this->value = $value;
    }
}
