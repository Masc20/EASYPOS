/**
 * EASYPOS Barcode Scanner Module
 * Listens for hardware barcode scanner inputs (rapid sequential keystrokes terminating in Enter).
 * Dispatches a standard 'easypos:barcode-scanned' event on window.
 */
export class BarcodeScanner {
    /**
     * @param {object} [options={}]
     * @param {number} [options.maxInterval=50] - Max milliseconds allowed between keystrokes to qualify as scanner input
     * @param {number} [options.minChars=3] - Minimum barcode length
     * @param {boolean} [options.preventInInputs=false] - Whether to ignore scans when an input element is active
     * @param {Function|null} [options.onScan=null] - Optional direct callback
     */
    constructor(options = {}) {
        this.buffer = '';
        this.lastTime = 0;
        this.maxInterval = options.maxInterval ?? 50;
        this.minChars = options.minChars ?? 3;
        this.preventInInputs = options.preventInInputs ?? false;
        this.onScan = options.onScan ?? null;
        this.enabled = true;
        this.boundHandler = this.handleKeyDown.bind(this);
    }

    /**
     * Start listening for scanner events.
     */
    start() {
        if (typeof window !== 'undefined') {
            window.addEventListener('keydown', this.boundHandler, true);
        }
    }

    /**
     * Stop listening for scanner events.
     */
    stop() {
        if (typeof window !== 'undefined') {
            window.removeEventListener('keydown', this.boundHandler, true);
        }
    }

    /**
     * Internal keydown event handler.
     * @param {KeyboardEvent} event
     */
    handleKeyDown(event) {
        if (!this.enabled) return;

        // Skip modifier keys
        if (event.ctrlKey || event.altKey || event.metaKey) return;

        if (this.preventInInputs) {
            const targetTag = (event.target?.tagName || '').toUpperCase();
            if (targetTag === 'INPUT' || targetTag === 'TEXTAREA') {
                return;
            }
        }

        const currentTime = Date.now();
        const interval = currentTime - this.lastTime;
        this.lastTime = currentTime;

        if (event.key === 'Enter') {
            if (this.buffer.length >= this.minChars) {
                event.preventDefault();
                event.stopPropagation();
                const barcode = this.buffer.trim();
                this.buffer = '';
                this.dispatchScan(barcode);
            } else {
                this.buffer = '';
            }
            return;
        }

        // Buffer printable single characters
        if (event.key.length === 1) {
            if (interval > this.maxInterval && this.buffer.length > 0) {
                // Interval exceeded: reset buffer (indicates human typing speed)
                this.buffer = '';
            }
            this.buffer += event.key;
        }
    }

    /**
     * Dispatch the scanned barcode.
     * @param {string} barcode
     */
    dispatchScan(barcode) {
        if (typeof this.onScan === 'function') {
            this.onScan(barcode);
        }

        window.dispatchEvent(
            new CustomEvent('easypos:barcode-scanned', {
                detail: { barcode },
            })
        );
    }
}
