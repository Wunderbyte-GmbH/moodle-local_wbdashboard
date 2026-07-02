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

namespace local_wb_dashboard\local\filter;

use renderer_base;

/**
 * Shared behaviour for filter controls.
 *
 * @package    local_wb_dashboard
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class base_filter implements filter_interface {
    /** @var string Logical filter key. */
    protected string $key;

    /** @var array Config (label, options, default, ...). */
    protected array $config;

    /**
     * Constructor.
     *
     * @param string $key
     * @param array $config
     */
    public function __construct(string $key, array $config = []) {
        $this->key = $key;
        $this->config = $config;
    }

    #[\Override]
    public function get_key(): string {
        return $this->key;
    }

    /**
     * The control's visible label (defaults to the key).
     *
     * @return string
     */
    protected function get_label(): string {
        return isset($this->config['label']) ? (string)$this->config['label'] : $this->key;
    }

    /**
     * The configured default value, if any.
     *
     * @return string
     */
    protected function get_default(): string {
        return isset($this->config['default']) ? (string)$this->config['default'] : '';
    }

    #[\Override]
    public function export_for_template(renderer_base $output): array {
        return [
            'key' => $this->key,
            'type' => $this->get_type(),
            'label' => format_string($this->get_label()),
            'id' => \html_writer::random_id('local-dashboard-filter-'),
            'value' => $this->get_default(),
        ];
    }
}
