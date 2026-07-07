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
 * Chart settings gear: opens the per-chart colour override modal form and asks
 * the affected chart to reload once the override is saved.
 *
 * @module     local_wb_dashboard/chart_settings
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import ModalForm from 'core_form/modalform';
import {getString} from 'core/str';

/** Broadcast to charts when their stored colours change (listened for in chart.js). */
const RELOAD_EVENT = 'local_wb_dashboard:chart-reload';

const TRIGGER = '[data-action="chart-settings"]';

/** One delegated document listener is enough for every gear on the page. */
let initialised = false;

/**
 * Pick a readable (#000/#fff) text colour for a given background hex.
 *
 * @param {String} value A #rgb or #rrggbb colour.
 * @return {String}
 */
const readableText = (value) => {
    const hex = String(value || '').trim().replace('#', '');
    const full = hex.length === 3 ? hex.split('').map((c) => c + c).join('') : hex;
    if (!/^[0-9a-fA-F]{6}$/.test(full)) {
        return '#000';
    }
    const r = parseInt(full.substring(0, 2), 16);
    const g = parseInt(full.substring(2, 4), 16);
    const b = parseInt(full.substring(4, 6), 16);
    // Perceived luminance: dark backgrounds get white text, light get black.
    return (0.299 * r + 0.587 * g + 0.114 * b) / 255 > 0.6 ? '#000' : '#fff';
};

/**
 * Give each palette dropdown (and its options) a colour swatch once rendered.
 */
const enhanceColourSelects = () => {
    document.querySelectorAll('select.local-wb-dashboard-colour').forEach((select) => {
        if (select.dataset.colourEnhanced === '1') {
            return;
        }
        select.dataset.colourEnhanced = '1';

        Array.prototype.forEach.call(select.options, (option) => {
            option.style.backgroundColor = option.value;
            option.style.color = readableText(option.value);
        });

        const paint = () => {
            select.style.backgroundColor = select.value;
            select.style.color = readableText(select.value);
        };
        select.addEventListener('change', paint);
        paint();
    });
};

/**
 * Open the settings modal for the chart the gear belongs to.
 *
 * @param {HTMLElement} trigger The gear button.
 */
const openModal = async(trigger) => {
    const chartid = trigger.dataset.chartid;
    const modalForm = new ModalForm({
        formClass: 'local_wb_dashboard\\form\\chart_settings_form',
        args: {chartid: chartid},
        modalConfig: {title: await getString('chartsettings:title', 'local_wb_dashboard')},
        returnFocus: trigger,
    });

    modalForm.addEventListener(modalForm.events.LOADED, enhanceColourSelects);
    modalForm.addEventListener(modalForm.events.FORM_SUBMITTED, (e) => {
        const detail = e.detail || {};
        document.dispatchEvent(new CustomEvent(RELOAD_EVENT, {
            detail: {chartid: detail.chartid || chartid},
        }));
    });

    modalForm.show();
};

export default {
    /**
     * Wire the gear buttons (idempotent; safe to call once per chart).
     */
    init: () => {
        if (initialised) {
            return;
        }
        initialised = true;
        document.addEventListener('click', (e) => {
            const trigger = e.target.closest(TRIGGER);
            if (trigger) {
                e.preventDefault();
                openModal(trigger);
            }
        });
    },
};
