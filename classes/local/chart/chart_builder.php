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

use local_wb_dashboard\local\dto\chart_data;
use local_wb_dashboard\local\dto\chart_series;

/**
 * Abstract fluent Builder that assembles a complete, Chart.js-ready chart_config.
 *
 * Concrete builders (one per chart family) implement apply_type_defaults() and,
 * where needed, override map_series_to_dataset(). build() returns the FULL config
 * so the AMD runtime only has to instantiate it.
 *
 * @package    local_wb_dashboard
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class chart_builder {
    /**
     * Default accessible, colourblind-friendly categorical palette (Okabe-Ito).
     *
     * @var string[]
     */
    protected const DEFAULT_PALETTE = [
        '#0072B2', '#E69F00', '#009E73', '#D55E00',
        '#CC79A7', '#56B4E9', '#F0E442', '#999999',
    ];

    /** @var chart_config The config being assembled. */
    protected chart_config $config;

    /** @var string[] Category / axis labels. */
    protected array $labels = [];

    /** @var chart_series[] The data series to render. */
    protected array $series = [];

    /** @var array Metadata carried from the DTO (title, centertext, axismax, ...). */
    protected array $meta = [];

    /** @var string[] Active colour palette. */
    protected array $palette;

    /** @var string|null Chart title. */
    protected ?string $title = null;

    /**
     * Constructor.
     */
    public function __construct() {
        $this->config = new chart_config();
        $this->palette = self::DEFAULT_PALETTE;
    }

    /**
     * Set the category/axis labels.
     *
     * @param string[] $labels
     * @return static
     */
    public function set_labels(array $labels): static {
        $this->labels = array_values($labels);
        return $this;
    }

    /**
     * Add a data series (mapped to a Chart.js dataset at build time).
     *
     * @param chart_series $series
     * @return static
     */
    public function add_dataset(chart_series $series): static {
        $this->series[] = $series;
        return $this;
    }

    /**
     * Override the default colour palette. Empty arrays are ignored.
     *
     * @param string[] $palette
     * @return static
     */
    public function set_colors(array $palette): static {
        $palette = array_values(array_filter($palette, static fn($c) => is_string($c) && $c !== ''));
        if (!empty($palette)) {
            $this->palette = $palette;
        }
        return $this;
    }

    /**
     * Set the chart title.
     *
     * @param string|null $title
     * @return static
     */
    public function set_title(?string $title): static {
        $this->title = ($title === null || $title === '') ? null : $title;
        return $this;
    }

    /**
     * Carry DTO metadata into the builder (title, centertext, axismax, ...).
     *
     * @param array $meta
     * @return static
     */
    public function set_meta(array $meta): static {
        $this->meta = $meta;
        if (!empty($meta['title']) && $this->title === null) {
            $this->title = (string)$meta['title'];
        }
        return $this;
    }

    /**
     * Convenience: load labels, series and meta from a DTO.
     *
     * @param chart_data $dto
     * @return static
     */
    public function set_data(chart_data $dto): static {
        $this->set_labels($dto->labels);
        foreach ($dto->series as $series) {
            $this->add_dataset($series);
        }
        $this->set_meta($dto->meta);
        return $this;
    }

    /**
     * Finalize and return the complete Chart.js config.
     *
     * @return chart_config
     */
    public function build(): chart_config {
        $this->apply_type_defaults();

        [$labels, $series] = $this->prepare_render_series();
        $this->config->data['labels'] = $labels;
        $this->config->data['datasets'] = [];
        foreach (array_values($series) as $index => $s) {
            $this->config->data['datasets'][] = $this->map_series_to_dataset($s, $index);
        }

        if ($this->title !== null) {
            $this->config->options['plugins']['title'] = [
                'display' => true,
                'text' => $this->title,
            ];
        }

        return $this->config;
    }

    /**
     * Apply the concrete type's presets (config->type + base options).
     */
    abstract protected function apply_type_defaults(): void;

    /**
     * Hook to reshape labels/series just before mapping to datasets. Default is
     * identity; builders that need a different topology (e.g. progress turns one
     * multi-point series into N single-point stacked datasets) override this.
     *
     * @return array{0: string[], 1: chart_series[]}
     */
    protected function prepare_render_series(): array {
        return [$this->labels, $this->series];
    }

    /**
     * Map one series to a Chart.js dataset. Default suits bar-family charts
     * (one colour per series); doughnut overrides for per-point colours.
     *
     * @param chart_series $series
     * @param int $index
     * @return array
     */
    protected function map_series_to_dataset(chart_series $series, int $index): array {
        $dataset = [
            'label' => $series->label,
            'data' => $series->data,
            'backgroundColor' => $this->resolve_series_color($series, $index),
        ];
        if ($series->stack !== null) {
            $dataset['stack'] = $series->stack;
        }
        return $dataset;
    }

    /**
     * Resolve a single colour for a bar-family series.
     *
     * @param chart_series $series
     * @param int $index
     * @return string
     */
    protected function resolve_series_color(chart_series $series, int $index): string {
        if (!empty($series->colors)) {
            return $series->colors[0];
        }
        return $this->palette[$index % count($this->palette)];
    }

    /**
     * Resolve a per-point colour array (doughnut/pie) of the given length.
     *
     * @param chart_series $series
     * @param int $count
     * @return string[]
     */
    protected function resolve_point_colors(chart_series $series, int $count): array {
        $colors = [];
        for ($i = 0; $i < $count; $i++) {
            if (isset($series->colors[$i])) {
                $colors[] = $series->colors[$i];
            } else {
                $colors[] = $this->palette[$i % count($this->palette)];
            }
        }
        return $colors;
    }
}
