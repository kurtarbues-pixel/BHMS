(() => {
    const storageKey = 'bhms-theme';
    let savedTheme = 'light';
    try {
        savedTheme = localStorage.getItem(storageKey) || 'light';
    } catch (error) {
        // The theme still works for this page when storage is unavailable.
    }

    const applyTheme = (theme) => {
        const dark = theme === 'dark';
        document.documentElement.dataset.theme = dark ? 'dark' : 'light';
        document.documentElement.setAttribute('data-bs-theme', dark ? 'dark' : 'light');
        document.querySelectorAll('.theme-toggle').forEach((button) => {
            button.setAttribute('aria-label', dark ? 'Switch to light mode' : 'Switch to dark mode');
            button.setAttribute('title', dark ? 'Switch to light mode' : 'Switch to dark mode');
            button.innerHTML = dark
                ? '<i class="bi bi-sun" aria-hidden="true"></i><span class="theme-toggle-label">Light mode</span>'
                : '<i class="bi bi-moon-stars" aria-hidden="true"></i><span class="theme-toggle-label">Dark mode</span>';
        });
    };

    applyTheme(savedTheme);
    document.addEventListener('DOMContentLoaded', () => {
        applyTheme(document.documentElement.dataset.theme);
        document.querySelectorAll('.theme-toggle').forEach((button) => {
            button.addEventListener('click', () => {
                const theme = document.documentElement.dataset.theme === 'dark' ? 'light' : 'dark';
                applyTheme(theme);
                try {
                    localStorage.setItem(storageKey, theme);
                } catch (error) {
                    // Keep the selected theme for the current page.
                }
            });
        });
    });
})();
