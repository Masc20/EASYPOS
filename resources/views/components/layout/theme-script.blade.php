{{--
  Anti-Flash Theme Engine Script
  Executes synchronously in <head> before DOM paint to prevent FOUC (flash of unstyled content)
  and preserves theme persistence across Livewire SPA navigations (wire:navigate).
--}}
<script>
    (function () {
        function applyTheme() {
            try {
                let theme = localStorage.getItem('theme');
                if (!theme) {
                    const match = document.cookie.match(/(^|;)\s*theme\s*=\s*([^;]+)/);
                    if (match) theme = match[2];
                }
                const prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
                const isDark = theme === 'dark' || (!theme && prefersDark);

                if (isDark) {
                    document.documentElement.classList.add('dark');
                } else {
                    document.documentElement.classList.remove('dark');
                }
            } catch (e) {}
        }

        // Apply immediately before first render
        applyTheme();

        // Re-apply immediately upon Livewire 3 SPA page navigation
        document.addEventListener('livewire:navigated', applyTheme);
        document.addEventListener('livewire:navigating', applyTheme);

        // Global fallback toggle function available immediately before bundled JS finishes loading
        if (typeof window.toggleTheme !== 'function') {
            window.toggleTheme = function () {
                try {
                    const isDark = document.documentElement.classList.contains('dark');
                    const next = isDark ? 'light' : 'dark';
                    if (next === 'dark') {
                        document.documentElement.classList.add('dark');
                    } else {
                        document.documentElement.classList.remove('dark');
                    }
                    localStorage.setItem('theme', next);
                    document.cookie = 'theme=' + next + ';path=/;max-age=31536000;SameSite=Lax';
                    window.dispatchEvent(new CustomEvent('easypos:theme-changed', { detail: { theme: next, isDark: next === 'dark' } }));
                } catch (e) {}
            };
        }
    })();
</script>
