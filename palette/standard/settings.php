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

/**
 * Colour-picker settings for the bundled standard dashboard palette.
 *
 * @package    wbdashboardpalette_standard
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($ADMIN->fulltree) {
    foreach (\wbdashboardpalette_standard\palette::DEFAULTS as $i => $default) {
        $n = $i + 1;
        $settings->add(new admin_setting_configcolourpicker(
            "wbdashboardpalette_standard/color{$n}",
            get_string('color', 'wbdashboardpalette_standard', $n),
            get_string('color_desc', 'wbdashboardpalette_standard', $n),
            $default
        ));
    }
}
