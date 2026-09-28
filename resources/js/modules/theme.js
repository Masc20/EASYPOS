/**
 * EASYPOS Theme Engine Module
 * Manages light / dark theme state with localStorage persistence,
 * cookie synchronization, system preference fallbacks, and Livewire SPA navigation support.
 */

const STORAGE_KEY = 'theme';

export const THEMES = Object.freeze({
    LIGHT: 'light',
    DARK: 'dark',
});

/**
 * Get the currently saved theme from localStorage or cookie fallback.
 * @returns {string|null} 'light', 'dark', or null if unset
 */
export function getSavedTheme() {
    try {
        const local = localStorage.getItem(STORAGE_KEY);
        if (local === THEMES.DARK || local === THEMES.LIGHT) {
            return local;
        }
        const match = document.cookie.match(/(^|;)\s*theme\s*=\s*([^;]+)/);
        if (match && (match[2] === THEMES.DARK || match[2] === THEMES.LIGHT)) {
            return match[2];
        }
    } catch {
        // Fallback for private browsing mode / restricted storage
    }
    return null;
}

/**
 * Check if the user's OS prefers dark mode.
 * @returns {boolean}
 */
export function prefersDarkMode() {
    return typeof window !== 'undefined' &&
        window.matchMedia &&
        window.matchMedia('(prefers-color-scheme: dark)').matches;
}

/**
 * Determine the active theme (persisted preference or OS preference).
 * @returns {string} 'light' or 'dark'
 */
export function getActiveTheme() {
    const saved = getSavedTheme();
    if (saved === THEMES.DARK || saved === THEMES.LIGHT) {
        return saved;
    }
    return prefersDarkMode() ? THEMES.DARK : THEMES.LIGHT;
}

/**
 * Apply theme to document element and persist choice across localStorage and cookie.
 * @param {string} theme - 'light' or 'dark'
 */
export function setTheme(theme) {
    const isDark = theme === THEMES.DARK;
    const root = document.documentElement;

    if (isDark) {
        root.classList.add('dark');
    } else {
        root.classList.remove('dark');
    }

    try {
        localStorage.setItem(STORAGE_KEY, theme);
        document.cookie = `${STORAGE_KEY}=${theme};path=/;max-age=31536000;SameSite=Lax`;
    } catch {
        // Fallback for private browsing mode / restricted storage
    }

    window.dispatchEvent(
        new CustomEvent('easypos:theme-changed', {
            detail: { theme, isDark },
        })
    );
}

/**
 * Synchronize the document element with the currently active theme.
 */
export function applyCurrentTheme() {
    const active = getActiveTheme();
    const root = document.documentElement;
    if (active === THEMES.DARK) {
        root.classList.add('dark');
    } else {
        root.classList.remove('dark');
    }
}

/**
 * Toggle between light and dark theme.
 * @returns {string} The newly activated theme ('light' or 'dark')
 */
export function toggleTheme() {
    const current = document.documentElement.classList.contains('dark')
        ? THEMES.DARK
        : THEMES.LIGHT;
    const nextTheme = current === THEMES.DARK ? THEMES.LIGHT : THEMES.DARK;
    setTheme(nextTheme);
    return nextTheme;
}

/**
 * Initialize theme listeners for OS preference changes and Livewire SPA navigation.
 */
export function initThemeWatcher() {
    if (typeof window === 'undefined') return;

    if (window.matchMedia) {
        const mediaQuery = window.matchMedia('(prefers-color-scheme: dark)');
        mediaQuery.addEventListener('change', (e) => {
            // Only react to OS changes if user hasn't explicitly set a preference
            if (!getSavedTheme()) {
                setTheme(e.matches ? THEMES.DARK : THEMES.LIGHT);
            }
        });
    }

    // Preserve dark mode across Livewire 3 SPA navigations (wire:navigate)
    document.addEventListener('livewire:navigated', applyCurrentTheme);
}
