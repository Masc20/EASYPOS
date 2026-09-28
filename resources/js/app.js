import './bootstrap';

import {
    getSavedTheme,
    getActiveTheme,
    setTheme,
    toggleTheme,
    initThemeWatcher,
    THEMES,
} from './modules/theme';

import { pinPad } from './components/pin-pad';
import { BarcodeScanner } from './modules/barcode-scanner';
import { formatCurrency, formatQuantity, formatDate } from './utils/formatters';

// Initialize background OS preference watcher
initThemeWatcher();

// Register Alpine.js components
const registerAlpineComponents = () => {
    if (window.Alpine && typeof window.Alpine.data === 'function') {
        window.Alpine.data('pinPad', pinPad);
    }
};

document.addEventListener('alpine:init', registerAlpineComponents);
if (window.Alpine) {
    registerAlpineComponents();
}

// Global EASYPOS application namespace
window.EASYPOS = {
    theme: {
        get: getSavedTheme,
        active: getActiveTheme,
        set: setTheme,
        toggle: toggleTheme,
        THEMES,
    },
    components: {
        pinPad,
    },
    formatters: {
        currency: formatCurrency,
        quantity: formatQuantity,
        date: formatDate,
    },
    BarcodeScanner,
};

// Backward compatibility alias for existing inline listeners
window.toggleTheme = toggleTheme;
