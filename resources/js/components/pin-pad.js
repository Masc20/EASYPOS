/**
 * EASYPOS PIN Pad Alpine.js Component
 * Provides 0ms tactile feedback, hardware keyboard listeners,
 * input validation, and automatic submission for floor terminals.
 *
 * @param {object} options
 * @param {object} options.wire - The Livewire wire proxy instance
 * @param {number} [options.maxDigits=4] - Expected PIN length
 * @returns {object} Alpine component specification
 */
export function pinPad({ wire = null, maxDigits = 4 } = {}) {
    return {
        pin: wire ? wire.entangle('pin') : '',
        maxDigits,
        isProcessing: false,

        /**
         * Current PIN length.
         * @type {number}
         */
        get length() {
            return (this.pin ?? '').length;
        },

        /**
         * Whether the PIN has reached the maximum required length.
         * @type {boolean}
         */
        get isComplete() {
            return this.length >= this.maxDigits;
        },

        /**
         * Append a numeric digit to the PIN.
         * @param {string|number} digit
         */
        press(digit) {
            if (this.length < this.maxDigits && !this.isProcessing) {
                this.pin = `${this.pin ?? ''}${digit}`;
                this.triggerHaptic();

                if (this.isComplete) {
                    this.submit();
                }
            }
        },

        /**
         * Delete the last entered digit.
         */
        backspace() {
            if (this.length > 0 && !this.isProcessing) {
                this.pin = (this.pin ?? '').slice(0, -1);
                this.triggerHaptic();
            }
        },

        /**
         * Clear the entire PIN.
         */
        clear() {
            this.pin = '';
            this.isProcessing = false;
        },

        /**
         * Submit the login credentials via Livewire.
         */
        submit() {
            if (this.isProcessing || !wire) return;

            this.isProcessing = true;
            try {
                const result = wire.login();
                if (result && typeof result.finally === 'function') {
                    result.finally(() => {
                        this.isProcessing = false;
                    });
                } else {
                    this.isProcessing = false;
                }
            } catch {
                this.isProcessing = false;
            }
        },

        /**
         * Populate credentials and trigger immediate login (used in dev/demo).
         * @param {string} empId
         * @param {string} pinVal
         */
        quickFill(empId, pinVal) {
            if (!wire) return;
            wire.set('emp_id', empId);
            this.pin = pinVal;
            this.submit();
        },

        /**
         * Window keyboard event handler.
         * Ignores keystrokes when the user is focused on form inputs.
         * @param {KeyboardEvent} event
         */
        handleKeydown(event) {
            const targetTag = (event.target?.tagName || '').toUpperCase();
            if (targetTag === 'INPUT' || targetTag === 'TEXTAREA' || targetTag === 'SELECT') {
                return;
            }

            if (/^[0-9]$/.test(event.key)) {
                event.preventDefault();
                this.press(event.key);
            } else if (event.key === 'Backspace') {
                event.preventDefault();
                this.backspace();
            } else if (event.key === 'Escape') {
                event.preventDefault();
                this.clear();
            }
        },

        /**
         * Light haptic vibration on mobile / kiosk touch devices.
         */
        triggerHaptic() {
            if (typeof navigator !== 'undefined' && 'vibrate' in navigator) {
                try {
                    navigator.vibrate(10);
                } catch {
                    // Ignore devices that disallow vibration without direct gesture
                }
            }
        },
    };
}
