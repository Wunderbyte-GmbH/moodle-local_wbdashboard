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

namespace local_wb_dashboard\plugininfo;

/**
 * Contract every dashboard palette subplugin (wbdashboardpalette_*) implements.
 *
 * A palette provides two independent things: a colour scheme (the chart colours
 * that replace the builder's default) and, separately, its own free-form
 * styles.css. Only the colour scheme is expressed here; the CSS is just the
 * subplugin's auto-loaded stylesheet.
 *
 * @package    local_wb_dashboard
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
interface wbdashboardpalette_interface {
    /**
     * Human-readable name for the settings dropdown.
     *
     * @return string
     */
    public function get_display_name(): string;

    /**
     * The ordered chart colour scheme (hex strings). Replaces the builder's
     * default palette; the builder cycles it by modulo, so any length is fine.
     *
     * @return string[]
     */
    public function get_colors(): array;
}
