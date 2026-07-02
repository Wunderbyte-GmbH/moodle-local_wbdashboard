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
 * Builds a complete bar chart config, covering the vertical, horizontal, grouped
 * stacked and progress variants (progress = horizontal stacked + fixed axis max).
 *
 * @package    local_wb_dashboard
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class bar_chart_builder extends chart_builder {
    /** @var bool Horizontal orientation (indexAxis = 'y'). */
    protected bool $horizontal = false;

    /** @var bool Stacked scales. */
    protected bool $stacked = false;

    /** @var float|null Fixed maximum on the value axis (progress bars). */
    protected ?float $axismax = null;

    /** @var bool Progress mode: expand one multi-point series into stacked segments. */
    protected bool $progress = false;

    /**
     * Set horizontal orientation.
     *
     * @param bool $horizontal
     * @return static
     */
    public function set_horizontal(bool $horizontal): static {
        $this->horizontal = $horizontal;
        return $this;
    }

    /**
     * Set stacked scales.
     *
     * @param bool $stacked
     * @return static
     */
    public function set_stacked(bool $stacked): static {
        $this->stacked = $stacked;
        return $this;
    }

    /**
     * Fix the value-axis maximum (used to fake a progress bar).
     *
     * @param float|null $max
     * @return static
     */
    public function set_axis_max(?float $max): static {
        $this->axismax = $max;
        return $this;
    }

    /**
     * Enable progress mode (a single multi-point series becomes stacked segments
     * of one bar with a fixed maximum).
     *
     * @param bool $progress
     * @return static
     */
    public function set_progress(bool $progress): static {
        $this->progress = $progress;
        return $this;
    }

    #[\Override]
    protected function apply_type_defaults(): void {
        // Allow DTO meta to drive the variant when the director did not set it explicitly.
        if (($this->meta['indexaxis'] ?? '') === 'y') {
            $this->horizontal = true;
        }
        if (!empty($this->meta['stacked'])) {
            $this->stacked = true;
        }
        if ($this->axismax === null && isset($this->meta['axismax'])) {
            $this->axismax = (float)$this->meta['axismax'];
        }

        // In progress mode, default the axis max to the total of the single series
        // so the bar represents 100% of the whole.
        if ($this->progress && $this->axismax === null && count($this->series) === 1) {
            $this->axismax = array_sum($this->series[0]->data);
        }

        $this->config->type = 'bar';

        // With indexAxis 'y' the value axis is 'x'; otherwise it is 'y'.
        $valueaxis = $this->horizontal ? 'x' : 'y';
        $categoryaxis = $this->horizontal ? 'y' : 'x';

        $valuescale = ['stacked' => $this->stacked, 'beginAtZero' => true];
        if ($this->axismax !== null) {
            $valuescale['max'] = $this->axismax;
        }

        $this->config->options = [
            'responsive' => true,
            'maintainAspectRatio' => false,
            'indexAxis' => $this->horizontal ? 'y' : 'x',
            'scales' => [
                $categoryaxis => ['stacked' => $this->stacked],
                $valueaxis => $valuescale,
            ],
            'plugins' => [
                'legend' => ['display' => $this->progress || count($this->series) > 1],
            ],
        ];

        if (!empty($this->meta['axistitles']) && is_array($this->meta['axistitles'])) {
            foreach (['x', 'y'] as $axis) {
                if (!empty($this->meta['axistitles'][$axis])) {
                    $this->config->options['scales'][$axis]['title'] = [
                        'display' => true,
                        'text' => (string)$this->meta['axistitles'][$axis],
                    ];
                }
            }
        }
    }

    #[\Override]
    protected function prepare_render_series(): array {
        // Progress: turn one multi-point series into one single-category bar with
        // one stacked segment (dataset) per point.
        if ($this->progress && count($this->series) === 1 && count($this->series[0]->data) > 1) {
            $source = $this->series[0];
            $segments = [];
            foreach ($source->data as $i => $value) {
                $segments[] = new chart_series(
                    $this->labels[$i] ?? (string)$i,
                    [$value],
                    isset($source->colors[$i]) ? [$source->colors[$i]] : [],
                    null,
                    'progress'
                );
            }
            return [[''], $segments];
        }
        return [$this->labels, $this->series];
    }
}
