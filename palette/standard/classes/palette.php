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

namespace wbdashboardpalette_standard;

use local_wb_dashboard\plugininfo\wbdashboardpalette_interface;

/**
 * The bundled standard palette: the accessible Okabe-Ito colour scheme.
 *
 * Reproduces the dashboard's original built-in colours, now admin-tunable via
 * this palette's settings. Serves as the guaranteed fallback palette.
 *
 * @package    wbdashboardpalette_standard
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class palette implements wbdashboardpalette_interface {
    /** Accessible, colourblind-friendly categorical defaults (Okabe-Ito). */
    public const DEFAULTS = [
        '#0072B2', '#E69F00', '#009E73', '#D55E00',
        '#CC79A7', '#56B4E9', '#F0E442', '#999999',
    ];

    #[\Override]
    public function get_display_name(): string {
        return get_string('pluginname', 'wbdashboardpalette_standard');
    }

    #[\Override]
    public function get_colors(): array {
        $colors = [];
        foreach (self::DEFAULTS as $i => $default) {
            $value = get_config('wbdashboardpalette_standard', 'color' . ($i + 1));
            $colors[] = (is_string($value) && $value !== '') ? $value : $default;
        }
        return $colors;
    }
}
