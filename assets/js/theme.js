/**
 * DomoGlass Pro - Module Thème
 * Gestion du sélecteur de palette
 */

import { UI } from './ui.js';

export const Theme = {
    palettes: [
        { id: 'midnight', label: 'Midnight', colors: ['#020617', '#6366f1'] },
        { id: 'ocean',    label: 'Océan',    colors: ['#082f49', '#0ea5e9'] },
        { id: 'purple',   label: 'Violet',   colors: ['#3b0764', '#a855f7'] },
        { id: 'rose',     label: 'Rose',     colors: ['#4c0519', '#fb7185'] },
        { id: 'emerald',  label: 'Émeraude', colors: ['#022c22', '#34d399'] },
        { id: 'light',    label: 'Clair',    colors: ['#f8fafc', '#4f46e5'] },
    ],

    set(paletteId) {
        UI.setPalette(paletteId);
    },
};

window.Theme = Theme;
