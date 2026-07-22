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

namespace local_wb_dashboard;

use local_wb_dashboard\local\dto\filter_constraint;
use local_wb_dashboard\local\filter\filter_factory;

/**
 * Tests for the filter Factory: creation, value normalization and neutral
 * constraint production.
 *
 * @package    local_wb_dashboard
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_wb_dashboard\local\filter\filter_factory
 * @covers     \local_wb_dashboard\local\filter\select_filter
 * @covers     \local_wb_dashboard\local\filter\date_filter
 * @covers     \local_wb_dashboard\local\filter\daterange_filter
 * @covers     \local_wb_dashboard\local\filter\number_filter
 * @covers     \local_wb_dashboard\local\filter\text_filter
 */
final class filter_factory_test extends \advanced_testcase {
    public function test_select_produces_equal_constraint(): void {
        $filter = filter_factory::create('select', 'courseid', ['options' => '1:A,2:B']);
        $this->assertSame('select', $filter->get_type());
        $constraint = $filter->to_constraint($filter->normalize_value('2'));
        $this->assertSame('courseid', $constraint->key);
        $this->assertSame(filter_constraint::OP_EQUAL, $constraint->operator);
        $this->assertSame('2', $constraint->value);
    }

    public function test_text_produces_contains_constraint(): void {
        $filter = filter_factory::create('text', 'name', []);
        $constraint = $filter->to_constraint($filter->normalize_value('  hi '));
        $this->assertSame(filter_constraint::OP_CONTAINS, $constraint->operator);
        $this->assertSame('hi', $constraint->value);
    }

    public function test_number_operator_from_config(): void {
        $filter = filter_factory::create('number', 'score', ['operator' => 'gte']);
        $constraint = $filter->to_constraint($filter->normalize_value('5'));
        $this->assertSame(filter_constraint::OP_GREATER_EQUAL, $constraint->operator);
        $this->assertSame(5.0, $constraint->value);
    }

    public function test_date_normalizes_iso_to_timestamp(): void {
        $filter = filter_factory::create('date', 'period', []);
        $value = $filter->normalize_value('2026-01-15');
        $this->assertIsInt($value);
        $this->assertGreaterThan(0, $value);
        $constraint = $filter->to_constraint($value);
        $this->assertSame(filter_constraint::OP_GREATER_EQUAL, $constraint->operator);
        $this->assertSame($value, $constraint->value);
    }

    public function test_daterange_produces_between_constraint(): void {
        $filter = filter_factory::create('daterange', 'period', []);
        $this->assertSame('daterange', $filter->get_type());
        $constraint = $filter->to_constraint($filter->normalize_value('2026-01-01|2026-06-30'));
        $this->assertSame(filter_constraint::OP_BETWEEN, $constraint->operator);
        $this->assertIsArray($constraint->value);
        $this->assertCount(2, $constraint->value);
    }

    public function test_unknown_type_throws(): void {
        $this->expectException(\moodle_exception::class);
        filter_factory::create('slider', 'x', []);
    }
}
