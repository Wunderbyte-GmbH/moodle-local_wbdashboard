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

use local_wb_dashboard\local\digits\digits_reducer;
use local_wb_dashboard\local\dto\chart_data;
use local_wb_dashboard\local\dto\chart_series;

/**
 * Tests for the digits reducer that collapses a chart_data DTO to one value.
 *
 * @package    local_wb_dashboard
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_wb_dashboard\local\digits\digits_reducer
 */
final class digits_reducer_test extends \basic_testcase {
    public function test_number_mode_sums_the_series(): void {
        $data = new chart_data();
        $data->add_series(new chart_series('Count', [2.0, 3.0, 5.0]));

        $result = digits_reducer::reduce($data, digits_reducer::MODE_NUMBER);

        $this->assertFalse($result->ispercent);
        $this->assertEqualsWithDelta(10.0, $result->value, 0.0001);
        $this->assertSame('Count', $result->label);
    }

    public function test_count_mode_is_an_alias_of_number(): void {
        $data = new chart_data();
        $data->add_series(new chart_series('', [4.0, 1.0]));

        $result = digits_reducer::reduce($data, digits_reducer::MODE_COUNT);

        $this->assertFalse($result->ispercent);
        $this->assertEqualsWithDelta(5.0, $result->value, 0.0001);
    }

    public function test_percent_uses_base_over_axismax(): void {
        // Two-report delta shaping: base=25, remainder=75, axismax(total)=100.
        $data = new chart_data();
        $data->add_series(new chart_series('', [25.0, 75.0]));
        $data->set_meta('axismax', 100.0);

        $result = digits_reducer::reduce($data, digits_reducer::MODE_PERCENT);

        $this->assertTrue($result->ispercent);
        $this->assertEqualsWithDelta(25.0, $result->value, 0.0001);
    }

    public function test_percent_two_report_ratio_uses_second_as_whole(): void {
        // No axismax meta (two-report totals): part = 30, whole = 120 -> 25%.
        $data = new chart_data();
        $data->add_series(new chart_series('', [30.0, 120.0]));

        $result = digits_reducer::reduce($data, digits_reducer::MODE_PERCENT);

        $this->assertTrue($result->ispercent);
        $this->assertEqualsWithDelta(25.0, $result->value, 0.0001);
    }

    public function test_percent_guards_divide_by_zero(): void {
        $data = new chart_data();
        $data->add_series(new chart_series('', [0.0, 0.0]));
        $data->set_meta('axismax', 0.0);

        $result = digits_reducer::reduce($data, digits_reducer::MODE_PERCENT);

        $this->assertTrue($result->ispercent);
        $this->assertEqualsWithDelta(0.0, $result->value, 0.0001);
    }

    public function test_empty_dto_reduces_to_zero(): void {
        $result = digits_reducer::reduce(new chart_data(), digits_reducer::MODE_NUMBER);

        $this->assertEqualsWithDelta(0.0, $result->value, 0.0001);
        $this->assertSame('', $result->label);
    }

    public function test_mode_validation(): void {
        $this->assertTrue(digits_reducer::is_valid_mode('number'));
        $this->assertTrue(digits_reducer::is_valid_mode('count'));
        $this->assertTrue(digits_reducer::is_valid_mode('percent'));
        $this->assertFalse(digits_reducer::is_valid_mode('bogus'));
    }
}
