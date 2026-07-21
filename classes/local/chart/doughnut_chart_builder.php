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

namespace local_wb_dashboard\local\chart;

use local_wb_dashboard\local\dto\chart_series;

/**
 * Builds a complete doughnut chart config.
 *
 * Reproduces the agenas doughnut: cut-out ring, no legend/tooltip, optional
 * centre text drawn client-side by the "centertext" JS-only plugin.
 *
 * @package    local_wb_dashboard
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class doughnut_chart_builder extends chart_builder {
    #[\Override]
    protected function apply_type_defaults(): void {
        $this->config->type = 'doughnut';
        $this->config->options = [
            'responsive' => true,
            'maintainAspectRatio' => false,
            'cutout' => '70%',
            'plugins' => [
                'legend' => ['display' => false],
                'tooltip' => ['enabled' => false],
            ],
        ];

        // Centre text (agenas behaviour): value + label drawn in the ring hole.
        if (isset($this->meta['centertext'])) {
            $this->config->plugins[] = 'centertext';
            $this->config->plugindata['centertext'] = [
                'value' => (string)$this->meta['centertext'],
                'label' => $this->labels[0] ?? '',
            ];
        }
    }

    #[\Override]
    protected function map_series_to_dataset(chart_series $series, int $index): array {
        return [
            'label' => $series->label,
            'data' => $series->data,
            'backgroundColor' => $this->resolve_point_colors($series, count($series->data)),
            'borderColor' => $this->palette[0],
        ];
    }
}
