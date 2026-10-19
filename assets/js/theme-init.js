/**
 * EODB-TaskFlow - Apply the Light / Dark theme before the page draws
 * (loaded in <head>, so the page does not flash the wrong theme).
 *
 * Order: the choice saved with the top-bar toggle (localStorage "eodbTheme"),
 * otherwise the device setting on the first visit, otherwise Light.
 * If browser storage is unavailable the page stays in Light Mode.
 * The toggle button itself is handled in app.js.
 */
(function () {
    'use strict';
    var theme = 'light';
    try {
        var saved = window.localStorage.getItem('eodbTheme');
        if (saved === 'dark' || saved === 'light') {
            theme = saved;
        } else if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
            theme = 'dark';
        }
    } catch (e) {
        theme = 'light';
    }
    document.documentElement.setAttribute('data-theme', theme);
    document.documentElement.setAttribute('data-bs-theme', theme);
})();
