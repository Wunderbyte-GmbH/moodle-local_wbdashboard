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

/**
 * The complete, Chart.js-ready chart configuration.
 *
 * This is the full output of the Builder. It mirrors the Chart.js config object
 * ({type, data, options}) plus a list of JS-only plugin NAMES the thin AMD
 * runtime resolves to functions. The AMD module builds no config of its own.
 *
 * @package    local_wb_dashboard
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class chart_config implements \JsonSerializable {
    /** @var string Chart.js base type ("bar" | "doughnut"). */
    public string $type = 'bar';

    /** @var array Chart.js data object: ['labels' => [], 'datasets' => []]. */
    public array $data = ['labels' => [], 'datasets' => []];

    /** @var array Fully-populated Chart.js options (scales, plugins, legend, ...). */
    public array $options = [];

    /** @var string[] JS-only plugin names to wire client-side (e.g. "centertext"). */
    public array $plugins = [];

    /** @var array Extra data JS-only plugins may read (e.g. center text value). */
    public array $plugindata = [];

    #[\Override]
    public function jsonSerialize(): array {
        return [
            'type' => $this->type,
            'data' => $this->data,
            'options' => $this->options,
            'plugins' => array_values($this->plugins),
            'plugindata' => $this->plugindata,
        ];
    }
}
