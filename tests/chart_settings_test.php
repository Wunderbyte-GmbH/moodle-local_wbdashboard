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

use local_wb_dashboard\local\settings\chart_settings;

/**
 * Tests for the per-chart colour override store.
 *
 * @package    local_wb_dashboard
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_wb_dashboard\local\settings\chart_settings
 */
final class chart_settings_test extends \advanced_testcase {
    public function test_get_returns_empty_when_unset(): void {
        $this->resetAfterTest();
        $this->assertSame([], chart_settings::get('cnothingsaved'));
    }

    public function test_save_and_get_roundtrip_sparse_map(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        chart_settings::save('cabc0001', [0 => '#FF0000', 2 => '#00ff00']);

        $stored = chart_settings::get('cabc0001');
        // Hex values are normalised to lower case; only the set slots are stored.
        $this->assertSame([0 => '#ff0000', 2 => '#00ff00'], $stored);
    }

    public function test_save_updates_existing_row(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        chart_settings::save('cabc0002', [0 => '#111111']);
        chart_settings::save('cabc0002', [1 => '#222222']);

        $this->assertSame([1 => '#222222'], chart_settings::get('cabc0002'));
    }

    public function test_save_empty_map_clears_override(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        chart_settings::save('cabc0003', [0 => '#123456']);
        chart_settings::save('cabc0003', []);

        $this->assertSame([], chart_settings::get('cabc0003'));
    }

    public function test_invalid_values_are_dropped_on_save(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        chart_settings::save('cabc0004', [0 => 'notacolour', 1 => '#abc']);

        // Only the valid short-hex slot survives.
        $this->assertSame([1 => '#abc'], chart_settings::get('cabc0004'));
    }

    public function test_resolve_merges_override_over_palette(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $palette = \wbdashboardpalette_standard\palette::DEFAULTS;
        chart_settings::save('cabc0005', [1 => '#abcdef']);

        $resolved = chart_settings::resolve('cabc0005');

        // Slot 1 is overridden; every other slot still follows the palette.
        $this->assertSame('#abcdef', $resolved[1]);
        $this->assertSame($palette[0], $resolved[0]);
        $this->assertSame($palette[2], $resolved[2]);
        $this->assertCount(count($palette), $resolved);
    }

    public function test_resolve_ignores_out_of_range_slots(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $palette = \wbdashboardpalette_standard\palette::DEFAULTS;
        // A slot index beyond the palette length must not extend the list.
        chart_settings::save('cabc0006', [999 => '#abcdef']);

        $this->assertSame($palette, chart_settings::resolve('cabc0006'));
    }
}
