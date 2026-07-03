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

namespace local_wb_dashboard\privacy;

use core_privacy\local\metadata\null_provider;

/**
 * Privacy provider for local_wb_dashboard.
 *
 * The plugin stores no personal data about viewers. Page filter selections are
 * held transiently in MUC only. Per-chart colour overrides
 * (local_wb_dashboard_chartcfg) are site-wide authoring config, not user data;
 * their usermodified column is incidental authoring metadata, not tracked here.
 *
 * @package    local_wb_dashboard
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements null_provider {
    #[\Override]
    public static function get_reason(): string {
        return 'privacy:metadata';
    }
}
