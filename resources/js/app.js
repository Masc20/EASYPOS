import './bootstrap';


import { pinPad } from './components/pin-pad';
import { formatCurrency, formatQuantity, formatDate } from './utils/formatters';


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
    components: {
        pinPad,
    },
    formatters: {
        currency: formatCurrency,
        quantity: formatQuantity,
        date: formatDate,
    },
};
