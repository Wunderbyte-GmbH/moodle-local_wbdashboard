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

/**
 * Date range filter control. The two visible date inputs are only proxies for a
 * hidden filter input carrying the combined "from|to" value: changing either
 * side writes the combined value into that input and dispatches a native
 * "change" event, so the existing filter bus (registered separately on the same
 * input) handles URL state, persistence and chart reloads. This module owns
 * nothing but the date inputs <-> hidden input wiring.
 *
 * When both sides are empty the combined value is the empty string (not "|"),
 * so the bus treats the filter as inactive and drops its URL parameter.
 *
 * @module     local_wb_dashboard/daterange
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Filterbus from 'local_wb_dashboard/filterbus';

/**
 * Whether a value can be shown in a date input: empty or ISO "YYYY-MM-DD".
 *
 * @param {String} value
 * @returns {Boolean}
 */
const displayable = (value) => value === '' || /^\d{4}-\d{2}-\d{2}$/.test(value);

/**
 * Write the combined "from|to" value into the hidden control and notify the
 * filter bus via a change event.
 *
 * @param {HTMLInputElement} input The hidden filter control.
 * @param {HTMLInputElement} from The visible from-date input.
 * @param {HTMLInputElement} to The visible to-date input.
 */
const compose = (input, from, to) => {
    input.value = (from.value === '' && to.value === '') ? '' : `${from.value}|${to.value}`;
    input.dispatchEvent(new Event('change', {bubbles: true}));
};

/**
 * Reflect the hidden control's value onto the two date inputs. A value without
 * a pipe is treated as the from side; a side a date input cannot display (e.g.
 * a raw timestamp) leaves that input empty.
 *
 * @param {HTMLInputElement} input The hidden filter control.
 * @param {HTMLInputElement} from The visible from-date input.
 * @param {HTMLInputElement} to The visible to-date input.
 */
const split = (input, from, to) => {
    const raw = input.value;
    const pipe = raw.indexOf('|');
    const fromraw = pipe === -1 ? raw : raw.slice(0, pipe);
    const toraw = pipe === -1 ? '' : raw.slice(pipe + 1);
    from.value = displayable(fromraw) ? fromraw : '';
    to.value = displayable(toraw) ? toraw : '';
};

export default {
    /**
     * Wire a date range control (identified by its hidden input id) to the
     * filter bus.
     *
     * @param {String} controlId The hidden input's DOM id.
     */
    init: (controlId) => {
        const input = document.getElementById(controlId);
        if (!input) {
            return;
        }
        const wrapper = input.closest('[data-region="chart-filter"]');
        if (!wrapper || wrapper.dataset.daterangeInitialised) {
            return;
        }
        wrapper.dataset.daterangeInitialised = '1';

        const from = wrapper.querySelector('[data-daterange-part="from"]');
        const to = wrapper.querySelector('[data-daterange-part="to"]');
        if (!from || !to) {
            return;
        }

        from.addEventListener('change', () => compose(input, from, to));
        to.addEventListener('change', () => compose(input, from, to));

        // Update the date inputs whenever the bus writes a value into the
        // hidden control (URL/cache seeding, or a sibling control bound to the
        // same filter key).
        input.addEventListener(Filterbus.eventTypes.reflect, () => split(input, from, to));

        // The filter bus may have seeded the input from URL/cache state before us.
        split(input, from, to);
    }
};
