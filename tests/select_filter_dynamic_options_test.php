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
use local_wb_dashboard\local\filter\filter_factory;

/**
 * Tests for dynamic select-filter options (optionsfield config).
 *
 * @package    local_wb_dashboard
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_wb_dashboard\local\filter\select_filter
 * @covers     \local_wb_dashboard\local\source\sources\reportbuilder\reportbuilder_source
 */
final class select_filter_dynamic_options_test extends \advanced_testcase {
    /**
     * Render the filter and return its options as a value => label map.
     *
     * @param array $config
     * @return array
     */
    private function export_options(array $config): array {
        global $PAGE;
        $filter = filter_factory::create('select', 'testkey', $config);
        $context = $filter->export_for_template($PAGE->get_renderer('core'));

        $options = [];
        foreach ($context['options'] as $option) {
            $options[$option['value']] = $option['label'];
        }
        return $options;
    }

    public function test_options_come_from_the_reports_select_filter(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        /** @var generator $rbgenerator */
        $rbgenerator = $this->getDataGenerator()->get_plugin_generator('core_reportbuilder');
        $report = $rbgenerator->create_report(['name' => 'Users', 'source' => users::class, 'default' => 0]);
        $rbgenerator->create_filter(['reportid' => $report->get('id'), 'uniqueidentifier' => 'user:country']);

        $options = $this->export_options([
            'report' => (string)$report->get('id'),
            'optionsfield' => 'country',
            // Static options are ignored when the dynamic lookup succeeds.
            'options' => 'x:Static',
        ]);

        $this->assertArrayHasKey('AT', $options);
        $this->assertArrayHasKey('DE', $options);
        $this->assertArrayNotHasKey('x', $options);
    }

    public function test_options_fall_back_to_distinct_row_values(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $this->getDataGenerator()->create_user(['firstname' => 'Ann']);
        $this->getDataGenerator()->create_user(['firstname' => 'Bob']);

        /** @var generator $rbgenerator */
        $rbgenerator = $this->getDataGenerator()->get_plugin_generator('core_reportbuilder');
        $report = $rbgenerator->create_report(['name' => 'Users', 'source' => users::class, 'default' => 0]);
        $rbgenerator->create_column(['reportid' => $report->get('id'), 'uniqueidentifier' => 'user:firstname']);
        // A text filter: no declared options, so row values are scanned.
        $rbgenerator->create_filter(['reportid' => $report->get('id'), 'uniqueidentifier' => 'user:firstname']);

        $config = ['report' => (string)$report->get('id'), 'optionsfield' => 'firstname'];
        $options = $this->export_options($config);

        $this->assertArrayHasKey('Ann', $options);
        $this->assertArrayHasKey('Bob', $options);

        // The option list is cached: a user created afterwards does not appear.
        $this->getDataGenerator()->create_user(['firstname' => 'Carl']);
        $options = $this->export_options($config);
        $this->assertArrayNotHasKey('Carl', $options);
    }

    public function test_no_report_access_falls_back_to_static_options(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        /** @var generator $rbgenerator */
        $rbgenerator = $this->getDataGenerator()->get_plugin_generator('core_reportbuilder');
        $report = $rbgenerator->create_report(['name' => 'Users', 'source' => users::class, 'default' => 0]);
        $rbgenerator->create_filter(['reportid' => $report->get('id'), 'uniqueidentifier' => 'user:country']);

        $this->setUser($this->getDataGenerator()->create_user());
        $options = $this->export_options([
            'report' => (string)$report->get('id'),
            'optionsfield' => 'country',
            'options' => 'a:Fallback A,b:Fallback B',
        ]);

        $this->assertSame(['a' => 'Fallback A', 'b' => 'Fallback B'], $options);
    }

    public function test_static_options_without_optionsfield_are_unchanged(): void {
        $this->resetAfterTest();

        $options = $this->export_options(['options' => 'one:One,two:Two']);

        $this->assertSame(['one' => 'One', 'two' => 'Two'], $options);
    }
}
