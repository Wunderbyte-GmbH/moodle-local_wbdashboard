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

use local_wb_dashboard\local\filter\page_filter_state;

/**
 * Tests for per-user page filter state persistence.
 *
 * @package    local_wb_dashboard
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_wb_dashboard\local\filter\page_filter_state
 */
final class page_filter_state_test extends \advanced_testcase {
    public function test_set_and_get_roundtrip(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        page_filter_state::set('teamdash', ['period' => '123', 'courseid' => '7']);

        $this->assertSame('123', page_filter_state::get_value('teamdash', 'period'));
        $this->assertSame('7', page_filter_state::get_value('teamdash', 'courseid'));
        $this->assertSame('fallback', page_filter_state::get_value('teamdash', 'missing', 'fallback'));
    }

    public function test_state_is_per_user_and_per_page(): void {
        $this->resetAfterTest();
        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();

        $this->setUser($user1);
        page_filter_state::set('dasha', ['k' => 'one']);

        $this->setUser($user2);
        $this->assertSame('', page_filter_state::get_value('dasha', 'k'));

        $this->setUser($user1);
        $this->assertSame('', page_filter_state::get_value('dashb', 'k'));
        $this->assertSame('one', page_filter_state::get_value('dasha', 'k'));
    }
}
