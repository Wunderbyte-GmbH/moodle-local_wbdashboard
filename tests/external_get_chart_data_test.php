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
use local_wb_dashboard\external\get_chart_data;

/**
 * Tests for the generic chart-data web service driving the full server pipeline.
 *
 * @package    local_wb_dashboard
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_wb_dashboard\external\get_chart_data
 * @covers     \local_wb_dashboard\local\source\reportbuilder_source
 */
final class external_get_chart_data_test extends \advanced_testcase {
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

    public function test_rows_mode_returns_full_config_payload(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $this->getDataGenerator()->create_user(['firstname' => 'Ann', 'lastname' => 'One']);
        $this->getDataGenerator()->create_user(['firstname' => 'Bob', 'lastname' => 'Two']);

        /** @var generator $rbgenerator */
        $rbgenerator = $this->getDataGenerator()->get_plugin_generator('core_reportbuilder');
        $report = $rbgenerator->create_report(['name' => 'Chart report', 'source' => users::class, 'default' => 0]);
        $rbgenerator->create_column(['reportid' => $report->get('id'), 'uniqueidentifier' => 'user:fullname']);

        $result = get_chart_data::execute(
            'reportbuilder',
            'bar',
            $this->pairs([
                'report' => $report->get('id'),
                'categoryfield' => 'user:fullname',
                'valuefield' => 'user:fullname',
            ]),
            [],
            [],
            ''
        );

        $config = json_decode($result['payload'], true);
        $this->assertSame('bar', $config['type']);
        $this->assertCount(1, $config['data']['datasets']);
        $this->assertNotEmpty($config['data']['labels']);
    }

    public function test_multi_report_count_one_bar_per_report(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->create_user();

        /** @var generator $rbgenerator */
        $rbgenerator = $this->getDataGenerator()->get_plugin_generator('core_reportbuilder');
        $reporta = $rbgenerator->create_report(['name' => 'Report A', 'source' => users::class, 'default' => 0]);
        $rbgenerator->create_column(['reportid' => $reporta->get('id'), 'uniqueidentifier' => 'user:fullname']);
        $reportb = $rbgenerator->create_report(['name' => 'Report B', 'source' => users::class, 'default' => 0]);
        $rbgenerator->create_column(['reportid' => $reportb->get('id'), 'uniqueidentifier' => 'user:fullname']);

        $result = get_chart_data::execute(
            'reportbuilder',
            'bar',
            $this->pairs([
                'reports' => $reporta->get('id') . ',' . $reportb->get('id'),
                'aggregation' => 'count',
            ]),
            [],
            [],
            ''
        );

        $config = json_decode($result['payload'], true);
        // One series, one bar per report, labelled with the report names.
        $this->assertSame(['Report A', 'Report B'], $config['data']['labels']);
        $this->assertCount(1, $config['data']['datasets']);
        $counts = $config['data']['datasets'][0]['data'];
        $this->assertCount(2, $counts);
        // Both reports use the same datasource, so the row counts match and cover
        // at least the two users created above.
        $this->assertSame($counts[0], $counts[1]);
        $this->assertGreaterThanOrEqual(2, $counts[0]);
    }

    public function test_unknown_source_throws(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $this->expectException(\moodle_exception::class);
        get_chart_data::execute('nosuchsource', 'bar', [], [], [], '');
    }
}
