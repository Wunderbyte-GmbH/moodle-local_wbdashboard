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

use local_wb_dashboard\local\definition\digits_definition;

/**
 * Tests for the digits definition (arg parsing and deterministic DOM id).
 *
 * @package    local_wb_dashboard
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_wb_dashboard\local\definition\digits_definition
 */
final class digits_definition_test extends \basic_testcase {
    public function test_reserved_keys_are_split_from_source_params(): void {
        $def = digits_definition::from_shortcode_args([
            'source' => 'reportbuilder',
            'display' => 'percent',
            'label' => 'Completed',
            'decimals' => '1',
            'unit' => 'pts',
            'consumes' => 'period,course',
            'pageid' => 'home',
            'idbase' => '5',
            'idtotal' => '6',
        ]);

        $this->assertSame('reportbuilder', $def->source);
        $this->assertSame('percent', $def->display);
        $this->assertSame('Completed', $def->displayopts['label']);
        $this->assertSame(1, $def->displayopts['decimals']);
        $this->assertSame('pts', $def->displayopts['unit']);
        $this->assertSame(['period', 'course'], $def->consumesfilters);
        $this->assertSame('home', $def->pageid);
        // Only non-reserved keys survive as source params.
        $this->assertSame(['idbase' => '5', 'idtotal' => '6'], $def->sourceparams);
    }

    public function test_defaults(): void {
        $def = digits_definition::from_shortcode_args(['source' => 'reportbuilder']);

        $this->assertSame('number', $def->display);
        $this->assertSame('default', $def->pageid);
        $this->assertSame(0, $def->displayopts['decimals']);
        $this->assertSame([], $def->consumesfilters);
    }

    public function test_domid_is_deterministic_and_prefixed(): void {
        $args = ['source' => 'reportbuilder', 'display' => 'count', 'reports' => '3'];

        $a = digits_definition::from_shortcode_args($args)->to_domid();
        $b = digits_definition::from_shortcode_args($args)->to_domid();

        $this->assertSame($a, $b);
        $this->assertStringStartsWith('local-dashboard-digits-', $a);
    }

    public function test_domid_ignores_source_param_order(): void {
        $one = digits_definition::from_shortcode_args(
            ['source' => 'reportbuilder', 'idbase' => '1', 'idtotal' => '2']
        )->to_domid();
        $two = digits_definition::from_shortcode_args(
            ['source' => 'reportbuilder', 'idtotal' => '2', 'idbase' => '1']
        )->to_domid();

        $this->assertSame($one, $two);
    }

    public function test_domid_differs_by_configuration(): void {
        $count = digits_definition::from_shortcode_args(
            ['source' => 'reportbuilder', 'display' => 'count', 'reports' => '3']
        )->to_domid();
        $percent = digits_definition::from_shortcode_args(
            ['source' => 'reportbuilder', 'display' => 'percent', 'reports' => '3']
        )->to_domid();

        $this->assertNotSame($count, $percent);
    }
}
