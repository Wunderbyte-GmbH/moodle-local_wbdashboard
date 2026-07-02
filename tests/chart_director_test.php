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

use local_wb_dashboard\local\chart\chart_director;
use local_wb_dashboard\local\dto\chart_data;
use local_wb_dashboard\local\dto\chart_series;

/**
 * Tests for the chart Builder (via the director), which must produce the full
 * Chart.js config for each semantic type.
 *
 * @package    local_wb_dashboard
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_wb_dashboard\local\chart\chart_director
 * @covers     \local_wb_dashboard\local\chart\doughnut_chart_builder
 * @covers     \local_wb_dashboard\local\chart\bar_chart_builder
 */
final class chart_director_test extends \advanced_testcase {
    /**
     * A two-value doughnut DTO (the agenas case).
     *
     * @return chart_data
     */
    private function doughnut_dto(): chart_data {
        $dto = new chart_data();
        $dto->set_labels(['Logged', 'Remaining']);
        $dto->add_series(new chart_series('', [8.0, 2.0]));
        $dto->set_meta('centertext', 8.0);
        $dto->set_meta('axismax', 10.0);
        return $dto;
    }

    /**
     * A multi-category, multi-series DTO.
     *
     * @return chart_data
     */
    private function multi_series_dto(): chart_data {
        $dto = new chart_data();
        $dto->set_labels(['Jan', 'Feb', 'Mar']);
        $dto->add_series(new chart_series('Open', [1.0, 2.0, 3.0], [], null, 'group'));
        $dto->add_series(new chart_series('Closed', [4.0, 5.0, 6.0], [], null, 'group'));
        return $dto;
    }

    public function test_doughnut_builds_full_config(): void {
        $config = (new chart_director())->build('doughnut', $this->doughnut_dto(), ['colors' => ['#111', '#222']]);
        $out = $config->jsonSerialize();

        $this->assertSame('doughnut', $out['type']);
        $this->assertSame('70%', $out['options']['cutout']);
        $this->assertContains('centertext', $out['plugins']);
        $this->assertSame('8', (string)$out['plugindata']['centertext']['value']);
        // One dataset, per-point colours from the supplied palette.
        $this->assertCount(1, $out['data']['datasets']);
        $this->assertSame(['#111', '#222'], $out['data']['datasets'][0]['backgroundColor']);
    }

    public function test_horizontalbar_sets_index_axis(): void {
        $config = (new chart_director())->build('horizontalbar', $this->multi_series_dto());
        $out = $config->jsonSerialize();

        $this->assertSame('bar', $out['type']);
        $this->assertSame('y', $out['options']['indexAxis']);
        $this->assertCount(2, $out['data']['datasets']);
    }

    public function test_stackedbar_stacks_and_keeps_groups(): void {
        $config = (new chart_director())->build('stackedbar', $this->multi_series_dto());
        $out = $config->jsonSerialize();

        $this->assertSame('bar', $out['type']);
        $this->assertTrue($out['options']['scales']['x']['stacked']);
        $this->assertTrue($out['options']['scales']['y']['stacked']);
        $this->assertSame('group', $out['data']['datasets'][0]['stack']);
    }

    public function test_progress_expands_to_stacked_segments_with_axis_max(): void {
        $config = (new chart_director())->build('progress', $this->doughnut_dto());
        $out = $config->jsonSerialize();

        $this->assertSame('bar', $out['type']);
        $this->assertSame('y', $out['options']['indexAxis']);
        // One multi-point series becomes N single-point stacked datasets.
        $this->assertCount(2, $out['data']['datasets']);
        $this->assertSame([''], $out['data']['labels']);
        $this->assertSame('progress', $out['data']['datasets'][0]['stack']);
        // Axis max defaults to the total (8 + 2).
        $this->assertEqualsWithDelta(10.0, $out['options']['scales']['x']['max'], 0.001);
    }

    public function test_unknown_type_throws(): void {
        $this->expectException(\moodle_exception::class);
        (new chart_director())->build('piechart3d', $this->multi_series_dto());
    }
}
