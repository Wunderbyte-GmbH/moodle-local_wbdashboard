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

namespace local_wb_dashboard\local\settings;

use local_wb_dashboard\local\palette\palette_manager;

/**
 * Per-chart display overrides, persisted site-wide (shared by all viewers).
 *
 * A chart is identified by a stable, deterministic id (see chart_definition).
 * Only the colour slots an author actually changed are stored, as a sparse
 * index => hex map; unset slots keep following the active palette. This lets a
 * chart deviate from the palette on some slots while inheriting the rest.
 *
 * @package    local_wb_dashboard
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class chart_settings {
    /** Database table holding the per-chart overrides. */
    private const TABLE = 'local_wb_dashboard_chartcfg';

    /**
     * The stored sparse colour override map for a chart (index => hex).
     *
     * @param string $chartid
     * @return array<int, string> Empty if the chart has no stored override.
     */
    public static function get(string $chartid): array {
        global $DB;

        $config = $DB->get_field(self::TABLE, 'config', ['chartid' => $chartid]);
        if ($config === false || !is_string($config) || $config === '') {
            return [];
        }
        $decoded = json_decode($config, true);
        if (!is_array($decoded) || !isset($decoded['colors']) || !is_array($decoded['colors'])) {
            return [];
        }

        $colors = [];
        foreach ($decoded['colors'] as $index => $hex) {
            if (is_numeric($index) && self::is_hex((string)$hex)) {
                $colors[(int)$index] = (string)$hex;
            }
        }
        return $colors;
    }

    /**
     * Persist (or clear) the sparse colour override map for a chart.
     *
     * An empty map removes any existing row so the chart reverts to the palette.
     *
     * @param string $chartid
     * @param array<int, string> $colors Sparse index => hex map.
     * @return void
     */
    public static function save(string $chartid, array $colors): void {
        global $DB, $USER;

        // Keep only valid, numerically-keyed hex values.
        $clean = [];
        foreach ($colors as $index => $hex) {
            if (is_numeric($index) && self::is_hex((string)$hex)) {
                $clean[(int)$index] = strtolower((string)$hex);
            }
        }
        ksort($clean);

        $existing = $DB->get_record(self::TABLE, ['chartid' => $chartid]);

        if (empty($clean)) {
            if ($existing) {
                $DB->delete_records(self::TABLE, ['id' => $existing->id]);
            }
            return;
        }

        $now = time();
        $config = json_encode(['colors' => $clean]);

        if ($existing) {
            $existing->config = $config;
            $existing->timemodified = $now;
            $existing->usermodified = (int)$USER->id;
            $DB->update_record(self::TABLE, $existing);
            return;
        }

        $DB->insert_record(self::TABLE, (object)[
            'chartid' => $chartid,
            'config' => $config,
            'timecreated' => $now,
            'timemodified' => $now,
            'usermodified' => (int)$USER->id,
        ]);
    }

    /**
     * The effective, full colour list for a chart: the stored sparse override
     * merged over the active palette. Unset slots inherit the palette colour.
     *
     * @param string $chartid
     * @return string[] Ordered colour list ready for the builder.
     */
    public static function resolve(string $chartid): array {
        $colors = palette_manager::colors();
        foreach (self::get($chartid) as $index => $hex) {
            if ($index >= 0 && $index < count($colors)) {
                $colors[$index] = $hex;
            }
        }
        return $colors;
    }

    /**
     * Whether a value is a #rgb or #rrggbb hex colour.
     *
     * @param string $value
     * @return bool
     */
    private static function is_hex(string $value): bool {
        return (bool)preg_match('/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', trim($value));
    }
}
