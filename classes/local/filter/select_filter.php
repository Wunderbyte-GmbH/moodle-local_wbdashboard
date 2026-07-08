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
use local_wb_dashboard\local\source\option_provider_interface;
use local_wb_dashboard\local\source\source_registry;
use renderer_base;

/**
 * A dropdown filter.
 *
 * Options come either from a static "value:Label,value:Label" config string or,
 * when optionsfield="..." is configured, dynamically from a data source that
 * implements {@see option_provider_interface} (source="..." selects it,
 * defaulting to reportbuilder). Static options act as the fallback whenever the
 * dynamic lookup yields nothing (unknown source, no permission, empty data).
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
    public function export_for_template(renderer_base $output): array {
        $context = parent::export_for_template($output);
        $default = $this->get_default();
        $context['options'] = array_map(static function (array $opt) use ($default): array {
            return [
                'value' => $opt['value'],
                'label' => $opt['label'],
                'selected' => ((string)$opt['value'] === $default),
            ];
        }, $this->resolve_options());
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
     * The option list: dynamic (optionsfield config) with static fallback.
     *
     * @return array<int, array{value: string, label: string}>
     */
    private function resolve_options(): array {
        $field = trim((string)($this->config['optionsfield'] ?? ''));
        if ($field !== '') {
            $options = $this->fetch_dynamic_options($field);
            if (!empty($options)) {
                return $options;
            }
        }
        return $this->parse_options();
    }

    /**
     * Fetch (cached) options from the configured source, degrading to [] when
     * the source is unknown, provides no options, or denies access.
     *
     * @param string $field Logical field/filter name to derive options from.
     * @return array<int, array{value: string, label: string}>
     */
    private function fetch_dynamic_options(string $field): array {
        $sourcename = trim((string)($this->config['source'] ?? 'reportbuilder'));
        if (!source_registry::exists($sourcename)) {
            return [];
        }
        $source = source_registry::get($sourcename);
        if (!$source instanceof option_provider_interface) {
            return [];
        }

        $sourceparams = array_intersect_key($this->config, array_flip($source->required_params()));
        try {
            // Per-user authorization always runs before the shared cache is read.
            $source->require_access($sourceparams);
        } catch (\Throwable $e) {
            return [];
        }

        $cache = \cache::make('local_wb_dashboard', 'filteroptions');
        $cachekey = sha1($sourcename . '|' . $field . '|' . json_encode($sourceparams));
        $options = $cache->get($cachekey);
        if ($options === false) {
            $options = $source->get_filter_options($sourceparams, $field);
            $cache->set($cachekey, $options);
        }
        return $options;
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
