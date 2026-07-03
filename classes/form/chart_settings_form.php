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

namespace local_wb_dashboard\form;

use context;
use context_system;
use core_form\dynamic_form;
use moodle_url;
use local_wb_dashboard\local\palette\palette_manager;
use local_wb_dashboard\local\settings\chart_settings;

/**
 * Per-chart colour override form, shown in a modal from the chart settings gear.
 *
 * One dropdown per palette slot: each lists the whole active palette, so an author
 * repoints a slot at any other palette colour. A slot left on its own palette
 * default is not stored, so it keeps following the active palette. The dropdowns
 * are given colour swatches client-side (see amd/src/chart_settings.js).
 *
 * @package    local_wb_dashboard
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class chart_settings_form extends dynamic_form {
    /**
     * Form definition: hidden chart id plus one palette dropdown per slot.
     */
    protected function definition() {
        $mform = $this->_form;

        $mform->addElement('hidden', 'chartid');
        $mform->setType('chartid', PARAM_ALPHANUMEXT);

        $mform->addElement('static', 'intro', '', get_string('chartsettings:intro', 'local_wb_dashboard'));

        $palette = self::palette();
        $options = self::palette_options($palette);
        foreach ($palette as $i => $default) {
            $mform->addElement(
                'select',
                "colour{$i}",
                get_string('chartsettings:colourslot', 'local_wb_dashboard', $i + 1),
                $options,
                ['class' => 'local-wb-dashboard-colour']
            );
            $mform->setDefault("colour{$i}", $default);
        }
    }

    /**
     * The context the form operates in.
     *
     * @return context
     */
    protected function get_context_for_dynamic_submission(): context {
        return context_system::instance();
    }

    /**
     * Only users who may configure charts can use this form.
     */
    protected function check_access_for_dynamic_submission(): void {
        require_capability('local/wb_dashboard:configurecharts', $this->get_context_for_dynamic_submission());
    }

    /**
     * Prefill each dropdown from any stored override for the incoming chart id.
     */
    public function set_data_for_dynamic_submission(): void {
        $chartid = $this->optional_param('chartid', '', PARAM_ALPHANUMEXT);
        $stored = chart_settings::get($chartid);

        $data = ['chartid' => $chartid];
        foreach (self::palette() as $i => $default) {
            $data["colour{$i}"] = $stored[$i] ?? $default;
        }
        $this->set_data($data);
    }

    /**
     * Validate each chosen colour is a member of the active palette.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files): array {
        $errors = [];
        $palette = self::palette();
        foreach (array_keys($palette) as $i) {
            $chosen = strtolower(trim((string)($data["colour{$i}"] ?? '')));
            if ($chosen !== '' && !in_array($chosen, $palette, true)) {
                $errors["colour{$i}"] = get_string('chartsettings:invalidcolour', 'local_wb_dashboard');
            }
        }
        return $errors;
    }

    /**
     * Persist the slots that deviate from their palette default and return the
     * effective colours.
     *
     * @return array
     */
    public function process_dynamic_submission() {
        $data = $this->get_data();

        $colors = [];
        foreach (self::palette() as $i => $default) {
            $chosen = strtolower((string)($data->{"colour{$i}"} ?? ''));
            // A slot left on its palette default stays unstored so it keeps
            // following the active palette.
            if ($chosen !== '' && $chosen !== $default) {
                $colors[$i] = $chosen;
            }
        }
        chart_settings::save($data->chartid, $colors);

        return [
            'chartid' => $data->chartid,
            'colors' => chart_settings::resolve($data->chartid),
        ];
    }

    /**
     * The page URL used when the form is rendered/submitted via AJAX.
     *
     * @return moodle_url
     */
    protected function get_page_url_for_dynamic_submission(): moodle_url {
        return new moodle_url('/local/wb_dashboard/');
    }

    /**
     * The active palette, normalised to lower-case hex so stored, default and
     * option values all compare cleanly.
     *
     * @return string[]
     */
    private static function palette(): array {
        return array_map('strtolower', palette_manager::colors());
    }

    /**
     * The palette as select options (hex => label).
     *
     * @param string[] $palette
     * @return array<string, string>
     */
    private static function palette_options(array $palette): array {
        $options = [];
        foreach ($palette as $j => $hex) {
            $options[$hex] = get_string('chartsettings:paletteoption', 'local_wb_dashboard', [
                'index' => $j + 1,
                'hex' => $hex,
            ]);
        }
        return $options;
    }
}
