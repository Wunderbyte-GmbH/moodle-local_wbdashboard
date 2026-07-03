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

use moodle_exception;

/**
 * Factory for filter controls.
 *
 * @package    local_wb_dashboard
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class filter_factory {
    /**
     * Whether a filter type is supported.
     *
     * @param string $type
     * @return bool
     */
    public static function exists(string $type): bool {
        return in_array($type, ['select', 'date', 'text', 'number', 'map'], true);
    }

    /**
     * Create a filter of the given type.
     *
     * @param string $type select|date|text|number
     * @param string $key Logical filter key.
     * @param array $config
     * @return filter_interface
     * @throws moodle_exception If the type is unknown.
     */
    public static function create(string $type, string $key, array $config = []): filter_interface {
        return match ($type) {
            'select' => new select_filter($key, $config),
            'date'   => new date_filter($key, $config),
            'text'   => new text_filter($key, $config),
            'number' => new number_filter($key, $config),
            'map'    => new map_filter($key, $config),
            default  => throw new moodle_exception('error:unknownfiltertype', 'local_wb_dashboard', '', $type),
        };
    }
}
