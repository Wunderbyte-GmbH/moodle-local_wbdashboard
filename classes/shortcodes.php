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

use core\output\html_writer;
use local_wb_dashboard\local\chart\chart_type;
use local_wb_dashboard\local\definition\chart_definition;
use local_wb_dashboard\local\definition\digits_definition;
use local_wb_dashboard\local\definition\filter_definition;
use local_wb_dashboard\local\digits\digits_reducer;
use local_wb_dashboard\local\filter\filter_factory;
use local_wb_dashboard\local\filter\page_filter_state;
use local_wb_dashboard\local\palette\palette_manager;
use local_wb_dashboard\local\source\source_registry;

/**
 * Shortcode handlers for local_wb_dashboard.
 *
 * @package    local_wb_dashboard
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class shortcodes {
    /**
     * [chart ...] — render a chart. Data is loaded client-side via the web service.
     *
     * @param string $shortcode
     * @param array $args
     * @param string|null $content
     * @param object $env
     * @param \Closure $next
     * @return string
     */
    public static function chart($shortcode, $args, $content, $env, $next): string {
        global $OUTPUT;

        $definition = chart_definition::from_shortcode_args($args);
        if (!source_registry::exists($definition->source)) {
            return get_string('error:unknownsource', 'local_wb_dashboard', s($definition->source));
        }
        if (!chart_type::is_valid($definition->type)) {
            return get_string('error:unknowncharttype', 'local_wb_dashboard', s($definition->type));
        }

        $ctx = $env->context ?? \context_system::instance();
        $chartid = self::chartid($definition, (int)$ctx->id);

        $wsargs = $definition->to_wsargs();
        $wsargs['chartid'] = $chartid;
        $title = $definition->displayopts['title'] ?? '';

        $context = [
            'canvasid' => html_writer::random_id('local-dashboard-chart-'),
            'chartid' => $chartid,
            'title' => $title !== '' ? $title : get_string('pluginname', 'local_wb_dashboard'),
            'width' => $definition->displayopts['width'] ?? 32.0,
            'height' => $definition->displayopts['height'] ?? 20.0,
            'pageid' => $definition->pageid,
            'consumes' => json_encode($definition->consumesfilters),
            'wsargs' => json_encode($wsargs),
            'palettename' => palette_manager::name(),
            'cansettings' => has_capability('local/wb_dashboard:configurecharts', $ctx),
        ];

        return $OUTPUT->render_from_template('local_wb_dashboard/chart', $context);
    }

    /**
     * Resolve the stable chart id for a definition in a context, disambiguating
     * identical charts on the same rendered page with an occurrence suffix.
     *
     * @param chart_definition $definition
     * @param int $contextid
     * @return string
     */
    private static function chartid(chart_definition $definition, int $contextid): string {
        static $seen = [];

        $base = $definition->chartid_base($contextid);
        $occurrence = $seen[$base] ?? 0;
        $seen[$base] = $occurrence + 1;

        return $occurrence === 0 ? $base : $base . '-' . $occurrence;
    }

    /**
     * [chartfilter ...] — render a page-level filter control.
     *
     * @param string $shortcode
     * @param array $args
     * @param string|null $content
     * @param object $env
     * @param \Closure $next
     * @return string
     */
    public static function chartfilter($shortcode, $args, $content, $env, $next): string {
        global $OUTPUT, $PAGE;

        $args = (array)$args;
        $definition = filter_definition::from_shortcode_args($args);

        if ($definition->key === '') {
            return get_string('error:missingfilterkey', 'local_wb_dashboard');
        }
        if (!filter_factory::exists($definition->type)) {
            return get_string('error:unknownfiltertype', 'local_wb_dashboard', s($definition->type));
        }

        $filter = filter_factory::create($definition->type, $definition->key, $definition->config);
        $context = $filter->export_for_template($PAGE->get_renderer('core'));

        // Prefill from persisted state (URL state overrides client-side).
        $context['value'] = page_filter_state::get_value(
            $definition->pageid,
            $definition->key,
            (string)($context['value'] ?? '')
        );
        // Reflect the prefilled value into select option selection.
        if (!empty($context['options'])) {
            foreach ($context['options'] as &$option) {
                $option['selected'] = ((string)$option['value'] === (string)$context['value']);
            }
            unset($option);
        }

        $context['pageid'] = $definition->pageid;
        $context['palettename'] = palette_manager::name();
        $context['isselect'] = ($definition->type === 'select');
        $context['isdate'] = ($definition->type === 'date');
        $context['istext'] = ($definition->type === 'text');
        $context['isnumber'] = ($definition->type === 'number');

        return $OUTPUT->render_from_template('local_wb_dashboard/chartfilter', $context);
    }

    /**
     * [digits ...] — render a single numeric value (number, count or percentage)
     * as a styleable DOM field. Data is loaded client-side via the web service.
     *
     * @param string $shortcode
     * @param array $args
     * @param string|null $content
     * @param object $env
     * @param \Closure $next
     * @return string
     */
    public static function digits($shortcode, $args, $content, $env, $next): string {
        global $OUTPUT;

        $args = (array)$args;
        $definition = digits_definition::from_shortcode_args($args);

        if ($definition->source === '') {
            return get_string('error:missingsource', 'local_wb_dashboard');
        }
        if (!source_registry::exists($definition->source)) {
            return get_string('error:unknownsource', 'local_wb_dashboard', s($definition->source));
        }
        if (!digits_reducer::is_valid_mode($definition->display)) {
            return get_string('error:unknowndisplaymode', 'local_wb_dashboard', s($definition->display));
        }

        $domid = $definition->to_domid();
        $context = [
            'domid' => $domid,
            'valueid' => $domid . '-value',
            'labelid' => $domid . '-label',
            'label' => $definition->displayopts['label'] ?? '',
            'pageid' => $definition->pageid,
            'consumes' => json_encode($definition->consumesfilters),
            'wsargs' => json_encode($definition->to_wsargs()),
            'palettename' => palette_manager::name(),
        ];

        return $OUTPUT->render_from_template('local_wb_dashboard/digits', $context);
    }
}
