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

namespace local_wb_dashboard\local\definition;

/**
 * A serializable, complete description of one chart.
 *
 * This is the drag-and-drop seam: the shortcode is one producer of a
 * chart_definition today; a future DB-backed dashboard builder is another.
 * Everything downstream (renderer, web service, source) consumes the
 * definition, never the raw shortcode args.
 *
 * @package    local_wb_dashboard
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class chart_definition {
    /** Reserved shortcode keys handled by the plugin (everything else = source param). */
    private const RESERVED = ['type', 'source', 'width', 'height', 'title', 'consumes', 'pageid'];

    /** @var string Source name (e.g. "reportbuilder"). */
    public string $source;

    /** @var string Semantic chart type (e.g. "doughnut", "stackedbar"). */
    public string $type;

    /** @var array Source-specific parameters (allowlisted by the source later). */
    public array $sourceparams;

    /** @var array Display options: colors, title, width, height. */
    public array $displayopts;

    /** @var string[] Logical filter keys this chart reacts to. Empty = every key its source maps. */
    public array $consumesfilters;

    /** @var string Page identifier the chart's filter state belongs to. */
    public string $pageid;

    /**
     * Constructor.
     *
     * @param string $source
     * @param string $type
     * @param array $sourceparams
     * @param array $displayopts
     * @param string[] $consumesfilters
     * @param string $pageid
     */
    public function __construct(
        string $source,
        string $type,
        array $sourceparams,
        array $displayopts,
        array $consumesfilters,
        string $pageid
    ) {
        $this->source = $source;
        $this->type = $type;
        $this->sourceparams = $sourceparams;
        $this->displayopts = $displayopts;
        $this->consumesfilters = $consumesfilters;
        $this->pageid = $pageid;
    }

    /**
     * Build a definition from raw [chart] shortcode arguments.
     *
     * Reserved keys are extracted; every remaining key becomes a source param.
     * Colours (color1, color2, ...) are collected into displayopts['colors'].
     *
     * @param array $args
     * @return self
     */
    public static function from_shortcode_args(array $args): self {
        $source = isset($args['source']) ? clean_param($args['source'], PARAM_ALPHANUMEXT) : '';
        $type = isset($args['type']) ? clean_param($args['type'], PARAM_ALPHA) : 'bar';
        $pageid = isset($args['pageid']) ? clean_param($args['pageid'], PARAM_ALPHANUMEXT) : 'default';

        $consumes = [];
        if (!empty($args['consumes'])) {
            foreach (explode(',', $args['consumes']) as $key) {
                $key = clean_param(trim($key), PARAM_ALPHANUMEXT);
                if ($key !== '') {
                    $consumes[] = $key;
                }
            }
        }

        // Collect colours in numeric order (color1, color2, ...).
        $colors = [];
        $colorkeys = array_filter(array_keys($args), static fn($k) => preg_match('/^color\d+$/', (string)$k));
        sort($colorkeys, SORT_NATURAL);
        foreach ($colorkeys as $ck) {
            $color = self::clean_color((string)$args[$ck]);
            if ($color !== '') {
                $colors[] = $color;
            }
        }

        $displayopts = [
            'colors' => $colors,
            'title'  => isset($args['title']) ? clean_param($args['title'], PARAM_TEXT) : '',
            'width'  => isset($args['width']) ? (float)$args['width'] : 32.0,
            'height' => isset($args['height']) ? (float)$args['height'] : 20.0,
            // Doughnut centre text is on by default; centertext=0 hides it.
            'centertext' => array_key_exists('centertext', $args)
                ? (bool)clean_param((string)$args['centertext'], PARAM_BOOL) : true,
        ];

        // Everything not reserved and not a colour key is a source parameter.
        $sourceparams = [];
        foreach ($args as $k => $v) {
            if (in_array($k, self::RESERVED, true) || preg_match('/^color\d+$/', (string)$k)) {
                continue;
            }
            $sourceparams[(string)$k] = (string)$v;
        }

        return new self($source, $type, $sourceparams, $displayopts, $consumes, $pageid);
    }

    /**
     * Serialize the parts the JS needs to ship to the web service.
     *
     * sourceparams are sent as a name/value list because keys vary per source.
     *
     * @return array
     */
    public function to_wsargs(): array {
        $pairs = [];
        foreach ($this->sourceparams as $name => $value) {
            $pairs[] = ['name' => $name, 'value' => (string)$value];
        }
        return [
            'source'       => $this->source,
            'type'         => $this->type,
            'pageid'       => $this->pageid,
            'sourceparams' => $pairs,
            'colors'       => $this->displayopts['colors'] ?? [],
            'title'        => $this->displayopts['title'] ?? '',
            'centertext'   => $this->displayopts['centertext'] ?? true,
        ];
    }

    /**
     * Validate and normalize a CSS colour argument (hex or simple name).
     *
     * @param string $value
     * @return string Empty string if invalid.
     */
    private static function clean_color(string $value): string {
        $value = trim($value);
        if (preg_match('/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $value)) {
            return $value;
        }
        if (preg_match('/^[a-zA-Z]{1,20}$/', $value)) {
            return $value;
        }
        return '';
    }
}
