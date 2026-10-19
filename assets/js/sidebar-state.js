/**
 * EODB-TaskFlow - Restore the collapsed/expanded sidebar before the page draws
 * (loaded in <head>, so the menu does not flicker). The toggle button itself
 * is handled in app.js. The choice is remembered per browser only.
 */
(function () {
    'use strict';
    try {
        if (window.localStorage.getItem('eodbSidebarCollapsed') === '1') {
            document.documentElement.classList.add('sidebar-collapsed');
        }
    } catch (e) {
        /* storage unavailable (private mode): keep the sidebar expanded */
    }
})();
