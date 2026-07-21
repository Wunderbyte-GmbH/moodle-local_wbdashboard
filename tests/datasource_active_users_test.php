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

declare(strict_types=1);

namespace local_wb_dashboard;

use core_reportbuilder_generator;
use core_reportbuilder\local\filters\date;
use core_reportbuilder\tests\core_reportbuilder_testcase;
use local_wb_dashboard\reportbuilder\datasource\active_users;

/**
 * Unit tests for the active users datasource.
 *
 * @package     local_wb_dashboard
 * @covers      \local_wb_dashboard\reportbuilder\datasource\active_users
 * @covers      \local_wb_dashboard\reportbuilder\local\entities\active_month
 * @copyright   2026 Wunderbyte GmbH
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class datasource_active_users_test extends core_reportbuilder_testcase {
    /** @var int 15 April 2026 10:00:00 UTC. */
    private const APRIL = 1776247200;

    /** @var int 15 May 2026 10:00:00 UTC. */
    private const MAY = 1778839200;

    /**
     * Insert a login event row into the standard logstore.
     *
     * @param int $userid
     * @param int $timecreated
     */
    private function insert_login(int $userid, int $timecreated): void {
        global $DB;

        $DB->insert_record('logstore_standard_log', (object) [
            'eventname' => '\\core\\event\\user_loggedin',
            'component' => 'core',
            'action' => 'loggedin',
            'target' => 'user',
            'objecttable' => 'user',
            'objectid' => $userid,
            'crud' => 'r',
            'edulevel' => 0,
            'contextid' => \context_system::instance()->id,
            'contextlevel' => CONTEXT_SYSTEM,
            'contextinstanceid' => 0,
            'userid' => $userid,
            'anonymous' => 0,
            'other' => serialize(['username' => "user{$userid}"]),
            'timecreated' => $timecreated,
            'origin' => 'web',
            'ip' => '0.0.0.0',
        ]);
    }

    /**
     * One row per user per month; repeated logins in a month collapse into it.
     */
    public function test_one_row_per_user_and_month(): void {
        $this->resetAfterTest();

        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->create_user(); // Never logged in: no rows.

        // User 1: twice in April, once in May. User 2: once in April.
        $this->insert_login((int) $user1->id, self::APRIL);
        $this->insert_login((int) $user1->id, self::APRIL + DAYSECS);
        $this->insert_login((int) $user1->id, self::MAY);
        $this->insert_login((int) $user2->id, self::APRIL);

        /** @var core_reportbuilder_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('core_reportbuilder');
        $report = $generator->create_report([
            'name' => 'Active users',
            'source' => active_users::class,
            'default' => false,
        ]);
        $generator->create_column(['reportid' => $report->get('id'), 'uniqueidentifier' => 'active_month:month']);
        $generator->create_column(['reportid' => $report->get('id'), 'uniqueidentifier' => 'user:username']);
        $generator->create_column(['reportid' => $report->get('id'), 'uniqueidentifier' => 'active_month:logins']);

        $content = $this->get_custom_report_content($report->get('id'));

        // Three user-months: user1/April (2 logins), user1/May, user2/April.
        $this->assertCount(3, $content);

        $byuserandmonth = [];
        foreach ($content as $row) {
            [$month, $username, $logins] = array_values($row);
            $byuserandmonth["{$username}/{$month}"] = (int) $logins;
        }

        $april = userdate(self::APRIL, get_string('strftimemonthyear', 'langconfig'));
        $may = userdate(self::MAY, get_string('strftimemonthyear', 'langconfig'));

        $this->assertEquals([
            "{$user1->username}/{$april}" => 2,
            "{$user1->username}/{$may}" => 1,
            "{$user2->username}/{$april}" => 1,
        ], $byuserandmonth);
    }

    /**
     * The month date filter restricts to months from the given date onwards.
     */
    public function test_month_filter(): void {
        $this->resetAfterTest();

        $user1 = $this->getDataGenerator()->create_user();
        $this->insert_login((int) $user1->id, self::APRIL);
        $this->insert_login((int) $user1->id, self::MAY);

        /** @var core_reportbuilder_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('core_reportbuilder');
        $report = $generator->create_report([
            'name' => 'Active users',
            'source' => active_users::class,
            'default' => false,
        ]);
        $generator->create_column(['reportid' => $report->get('id'), 'uniqueidentifier' => 'active_month:month']);
        $generator->create_filter(['reportid' => $report->get('id'), 'uniqueidentifier' => 'active_month:month']);

        // From 20 April onwards: April's month start falls before the cutoff,
        // May's after it, so only the May row remains.
        $content = $this->get_custom_report_content($report->get('id'), 30, [
            'active_month:month_operator' => date::DATE_RANGE,
            'active_month:month_from' => self::APRIL + (5 * DAYSECS),
        ]);

        $this->assertCount(1, $content);
        $this->assertEquals(
            userdate(self::MAY, get_string('strftimemonthyear', 'langconfig')),
            reset($content[0])
        );
    }
}
