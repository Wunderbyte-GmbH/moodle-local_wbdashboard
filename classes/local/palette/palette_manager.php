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

namespace local_wb_dashboard\local\palette;

use core_plugin_manager;
use local_wb_dashboard\plugininfo\wbdashboardpalette_interface;

/**
 * Resolves the active dashboard palette and its colours.
 *
 * The active palette is a single site-wide choice stored by name in the
 * `local_wb_dashboard/activepalette` setting. The class is built directly from
 * that name (`\wbdashboardpalette_<name>\palette`) — the chosen name IS the
 * namespace, so no enumeration is needed to resolve it. Enumeration is used only
 * to list the installed palettes for the settings dropdown.
 *
 * @package    local_wb_dashboard
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class palette_manager {
    /** The bundled palette used as the default and ultimate fallback. */
    public const DEFAULT_PALETTE = 'standard';

    /**
     * The active palette's name (as chosen in settings), defaulting to the bundled palette.
     *
     * @return string
     */
    public static function name(): string {
        $name = get_config('local_wb_dashboard', 'activepalette');
        return (is_string($name) && $name !== '') ? $name : self::DEFAULT_PALETTE;
    }

    /**
     * The active palette instance, falling back to the bundled standard palette
     * if the configured palette is missing or invalid.
     *
     * @return wbdashboardpalette_interface
     */
    public static function active(): wbdashboardpalette_interface {
        return self::instance(self::name()) ?? self::instance(self::DEFAULT_PALETTE);
    }

    /**
     * Instantiate a palette by name, or null if it does not exist / is invalid.
     *
     * @param string $name
     * @return wbdashboardpalette_interface|null
     */
    public static function instance(string $name): ?wbdashboardpalette_interface {
        $class = "\\wbdashboardpalette_{$name}\\palette";
        if (!class_exists($class)) {
            return null;
        }
        $palette = new $class();
        return ($palette instanceof wbdashboardpalette_interface) ? $palette : null;
    }

    /**
     * All installed palettes as [name => display name], for the settings dropdown.
     *
     * @return array<string,string>
     */
    public static function available(): array {
        $options = [];
        foreach (core_plugin_manager::instance()->get_plugins_of_type('wbdashboardpalette') as $plugin) {
            $palette = self::instance($plugin->name);
            if ($palette !== null) {
                $options[$plugin->name] = $palette->get_display_name();
            }
        }
        return $options;
    }

    /**
     * The active palette's colour scheme.
     *
     * @return string[]
     */
    public static function colors(): array {
        return self::active()->get_colors();
    }
}
