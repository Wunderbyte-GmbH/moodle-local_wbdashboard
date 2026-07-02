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

use core_reportbuilder\generator;
use core_user\reportbuilder\datasource\users;
use local_wb_dashboard\external\get_digits_data;

/**
 * Tests for the digits web service driving the full server pipeline.
 *
 * @package    local_wb_dashboard
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_wb_dashboard\external\get_digits_data
 * @covers     \local_wb_dashboard\local\source\pipeline
 */
final class external_get_digits_data_test extends \advanced_testcase {
    /**
     * Turn an associative array into the WS name/value pair list.
     *
     * @param array $params
     * @return array
     */
    private function pairs(array $params): array {
        $pairs = [];
        foreach ($params as $name => $value) {
            $pairs[] = ['name' => $name, 'value' => (string)$value];
        }
        return $pairs;
    }

    public function test_count_mode_returns_row_count(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->create_user();

        /** @var generator $rbgenerator */
        $rbgenerator = $this->getDataGenerator()->get_plugin_generator('core_reportbuilder');
        $report = $rbgenerator->create_report(['name' => 'Users', 'source' => users::class, 'default' => 0]);
        $rbgenerator->create_column(['reportid' => $report->get('id'), 'uniqueidentifier' => 'user:fullname']);

        $result = get_digits_data::execute(
            'reportbuilder',
            'count',
            $this->pairs([
                'reports' => $report->get('id'),
                'aggregation' => 'count',
            ])
        );

        $this->assertFalse($result['ispercent']);
        // At least the two users created above (plus the admin/guest).
        $this->assertGreaterThanOrEqual(2.0, $result['value']);
        $this->assertSame((string)$result['value'], (string)(float)$result['formatted']);
    }

    public function test_percent_mode_from_two_report_delta(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->create_user();

        /** @var generator $rbgenerator */
        $rbgenerator = $this->getDataGenerator()->get_plugin_generator('core_reportbuilder');
        // Both reports use the same datasource, so base == total -> 100%.
        $report = $rbgenerator->create_report(['name' => 'Delta', 'source' => users::class, 'default' => 0]);
        $rbgenerator->create_column(['reportid' => $report->get('id'), 'uniqueidentifier' => 'user:fullname']);

        $result = get_digits_data::execute(
            'reportbuilder',
            'percent',
            $this->pairs([
                'idbase' => $report->get('id'),
                'fieldbase' => 'user:fullname',
                'idtotal' => $report->get('id'),
                'fieldtotal' => 'user:fullname',
            ])
        );

        $this->assertTrue($result['ispercent']);
        $this->assertStringEndsWith('%', $result['formatted']);
    }

    public function test_percent_mode_from_two_report_count_ratio(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->create_user();

        /** @var generator $rbgenerator */
        $rbgenerator = $this->getDataGenerator()->get_plugin_generator('core_reportbuilder');
        // Both reports use the same datasource, so part count == whole count -> 100%.
        $part = $rbgenerator->create_report(['name' => 'Part', 'source' => users::class, 'default' => 0]);
        $rbgenerator->create_column(['reportid' => $part->get('id'), 'uniqueidentifier' => 'user:fullname']);
        $whole = $rbgenerator->create_report(['name' => 'Whole', 'source' => users::class, 'default' => 0]);
        $rbgenerator->create_column(['reportid' => $whole->get('id'), 'uniqueidentifier' => 'user:fullname']);

        $result = get_digits_data::execute(
            'reportbuilder',
            'percent',
            $this->pairs([
                'reports' => $part->get('id') . ',' . $whole->get('id'),
                'aggregation' => 'count',
            ])
        );

        $this->assertTrue($result['ispercent']);
        $this->assertEqualsWithDelta(100.0, $result['value'], 0.0001);
        $this->assertStringEndsWith('%', $result['formatted']);
    }

    public function test_label_override_is_used(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        /** @var generator $rbgenerator */
        $rbgenerator = $this->getDataGenerator()->get_plugin_generator('core_reportbuilder');
        $report = $rbgenerator->create_report(['name' => 'Users', 'source' => users::class, 'default' => 0]);
        $rbgenerator->create_column(['reportid' => $report->get('id'), 'uniqueidentifier' => 'user:fullname']);

        $result = get_digits_data::execute(
            'reportbuilder',
            'count',
            $this->pairs(['reports' => $report->get('id'), 'aggregation' => 'count']),
            [],
            'Active users'
        );

        $this->assertSame('Active users', $result['label']);
    }

    public function test_unknown_display_mode_throws(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $this->expectException(\moodle_exception::class);
        get_digits_data::execute('reportbuilder', 'bogus', []);
    }

    public function test_unknown_source_throws(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $this->expectException(\moodle_exception::class);
        get_digits_data::execute('nosuchsource', 'number', []);
    }
}
