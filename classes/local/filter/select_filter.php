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

namespace local_wb_dashboard\local\filter;

use local_wb_dashboard\local\dto\filter_constraint;
use renderer_base;

/**
 * A dropdown filter. Options are configured as "value:Label,value:Label".
 *
 * @package    local_wb_dashboard
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class select_filter extends base_filter {
    #[\Override]
    public function get_type(): string {
        return 'select';
    }

    #[\Override]
    public function get_template(): string {
        return 'local_wb_dashboard/filter_select';
    }

    #[\Override]
    public function export_for_template(renderer_base $output): array {
        $context = parent::export_for_template($output);
        $default = $this->get_default();
        $context['options'] = array_map(static function (array $opt) use ($default): array {
            return [
                'value' => $opt['value'],
                'label' => $opt['label'],
                'selected' => ((string)$opt['value'] === $default),
            ];
        }, $this->parse_options());
        return $context;
    }

    #[\Override]
    public function normalize_value($raw) {
        return clean_param((string)$raw, PARAM_RAW_TRIMMED);
    }

    #[\Override]
    public function to_constraint($value): filter_constraint {
        return new filter_constraint($this->key, filter_constraint::OP_EQUAL, $value);
    }

    /**
     * Parse the "value:Label,value:Label" options string into a list.
     *
     * @return array<int, array{value: string, label: string}>
     */
    private function parse_options(): array {
        $raw = (string)($this->config['options'] ?? '');
        $options = [];
        foreach (array_filter(array_map('trim', explode(',', $raw))) as $pair) {
            if (strpos($pair, ':') !== false) {
                [$value, $label] = explode(':', $pair, 2);
            } else {
                $value = $label = $pair;
            }
            $options[] = [
                'value' => clean_param(trim($value), PARAM_RAW_TRIMMED),
                'label' => format_string(trim($label)),
            ];
        }
        return $options;
    }
}
