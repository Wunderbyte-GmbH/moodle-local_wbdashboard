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

namespace local_wb_dashboard\reportbuilder\datasource;

use coding_exception;
use core_reportbuilder\datasource;
use core_reportbuilder\local\entities\user;
use core_reportbuilder\local\helpers\database;
use local_wb_dashboard\reportbuilder\local\entities\active_month;

/**
 * Unique active users datasource.
 *
 * One row per user per calendar month with at least one login: user 7 logging
 * in three times in April yields a single "user 7 / April" row. Counting rows
 * per month therefore counts unique active users per month.
 *
 * Reads login events (\core\event\user_loggedin) from the standard logstore, so
 * history is bounded by that store being enabled and by its retention setting
 * ("Keep logs for" in the Standard log settings).
 *
 * @package    local_wb_dashboard
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class active_users extends datasource {

    /**
     * Return user friendly name of the datasource
     *
     * @return string
     */
    public static function get_name(): string {
        return get_string('datasource:activeusers', 'local_wb_dashboard');
    }

    /**
     * Initialise report
     */
    protected function initialise(): void {
        global $CFG;

        $userentity = new user();
        $useralias = $userentity->get_table_alias('user');

        $this->set_main_table('user', $useralias);
        $this->add_entity($userentity);

        $userparamguest = database::generate_param_name();
        $this->add_base_condition_sql("{$useralias}.id != :{$userparamguest} AND {$useralias}.deleted = 0", [
            $userparamguest => $CFG->siteguest,
        ]);

        // One row per user and calendar month with at least one login. The inner
        // join also restricts the report to users who logged in at least once.
        $monthentity = new active_month();
        $monthalias = $monthentity->get_table_alias('logstore_standard_log');
        $logalias = database::generate_alias();
        $monthsql = self::sql_month_start("{$logalias}.timecreated");

        $this->add_join("
            JOIN (SELECT {$logalias}.userid,
                         {$monthsql} AS monthstart,
                         COUNT({$logalias}.id) AS logins,
                         MIN({$logalias}.timecreated) AS firstlogin,
                         MAX({$logalias}.timecreated) AS lastlogin
                    FROM {logstore_standard_log} {$logalias}
                   WHERE {$logalias}.component = 'core'
                         AND {$logalias}.action = 'loggedin'
                         AND {$logalias}.target = 'user'
                         AND {$logalias}.anonymous = 0
                GROUP BY {$logalias}.userid, {$monthsql}
                 ) {$monthalias} ON {$monthalias}.userid = {$useralias}.id");

        $this->add_entity($monthentity);

        $this->add_all_from_entity($monthentity->get_entity_name());
        $this->add_all_from_entity($userentity->get_entity_name());
    }

    /**
     * Cross-database SQL returning the unix timestamp of the first second of the
     * calendar month (in server timezone) that the given unix timestamp field
     * falls into.
     *
     * @param string $field SQL expression holding a unix timestamp
     * @return string
     * @throws coding_exception for database families without an implementation
     */
    private static function sql_month_start(string $field): string {
        global $DB;

        switch ($DB->get_dbfamily()) {
            case 'postgres':
                return "CAST(EXTRACT(EPOCH FROM DATE_TRUNC('month', TO_TIMESTAMP({$field}))) AS BIGINT)";
            case 'mysql':
                return "UNIX_TIMESTAMP(DATE_FORMAT(FROM_UNIXTIME({$field}), '%Y-%m-01'))";
            default:
                throw new coding_exception('The active users datasource does not support this database family: ' .
                    $DB->get_dbfamily());
        }
    }

    /**
     * Return the columns that will be added to the report once is created
     *
     * @return string[]
     */
    public function get_default_columns(): array {
        return ['active_month:month', 'user:fullname'];
    }

    /**
     * Return the filters that will be added to the report once is created
     *
     * @return string[]
     */
    public function get_default_filters(): array {
        return ['active_month:month'];
    }

    /**
     * Return the conditions that will be added to the report once is created
     *
     * @return string[]
     */
    public function get_default_conditions(): array {
        return [];
    }

    /**
     * Return the default sorting that will be added to the report once it is created
     *
     * @return int[]
     */
    public function get_default_column_sorting(): array {
        return [
            'active_month:month' => SORT_ASC,
        ];
    }
}
