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

namespace local_wb_dashboard;

use local_wb_dashboard\local\palette\palette_manager;
use local_wb_dashboard\plugininfo\wbdashboardpalette_interface;

/**
 * Tests for the palette manager and the bundled standard palette.
 *
 * @package    local_wb_dashboard
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_wb_dashboard\local\palette\palette_manager
 * @covers     \wbdashboardpalette_standard\palette
 */
final class palette_manager_test extends \advanced_testcase {
    public function test_name_defaults_to_standard_when_unset(): void {
        $this->resetAfterTest();
        $this->assertSame('standard', palette_manager::name());
    }

    public function test_name_reads_config(): void {
        $this->resetAfterTest();
        set_config('activepalette', 'acme', 'local_wb_dashboard');
        $this->assertSame('acme', palette_manager::name());
    }

    public function test_active_falls_back_to_standard_when_palette_missing(): void {
        $this->resetAfterTest();
        // A palette that is not installed must not break resolution.
        set_config('activepalette', 'doesnotexist', 'local_wb_dashboard');
        $palette = palette_manager::active();
        $this->assertInstanceOf(wbdashboardpalette_interface::class, $palette);
        $this->assertInstanceOf(\wbdashboardpalette_standard\palette::class, $palette);
    }

    public function test_available_includes_standard(): void {
        $this->resetAfterTest();
        $this->assertArrayHasKey('standard', palette_manager::available());
    }

    public function test_colors_returns_okabe_ito_defaults(): void {
        $this->resetAfterTest();
        $colors = palette_manager::colors();
        $this->assertSame(\wbdashboardpalette_standard\palette::DEFAULTS, $colors);
    }

    public function test_admin_override_is_reflected_in_colors(): void {
        $this->resetAfterTest();
        set_config('color1', '#123456', 'wbdashboardpalette_standard');
        $colors = palette_manager::colors();
        $this->assertSame('#123456', $colors[0]);
        // Unset slots keep their built-in default.
        $this->assertSame(\wbdashboardpalette_standard\palette::DEFAULTS[1], $colors[1]);
    }
}
