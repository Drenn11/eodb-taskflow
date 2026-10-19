/**
 * EODB-TaskFlow - Shared page behaviour
 * - Sidebar open/close on tablets and phones; collapse/expand on desktop
 * - Light / Dark Mode toggle in the top bar
 * - Confirmation prompt for buttons/forms with a data-confirm attribute
 * - Show/hide toggles for password fields (data-toggle-password="#fieldId")
 * - Double-submit prevention (with a loading state) for forms with data-once
 * - Notification badge refresh every 60 seconds
 */
(function () {
    'use strict';

    // Sidebar (small screens)
    var sidebar = document.getElementById('appSidebar');
    var backdrop = document.getElementById('sidebarBackdrop');
    var toggle = document.getElementById('sidebarToggle');

    function setSidebar(open) {
        if (!sidebar) {
            return;
        }
        var wasOpen = sidebar.classList.contains('open');
        sidebar.classList.toggle('open', open);
        if (backdrop) {
            backdrop.classList.toggle('show', open);
        }
        if (toggle) {
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        }
        // Keyboard users: move focus into the panel when it opens and back to
        // the menu button when it closes.
        if (open && !wasOpen) {
            var firstLink = sidebar.querySelector('.sidebar-link');
            if (firstLink) {
                firstLink.focus();
            }
        } else if (!open && wasOpen && toggle) {
            toggle.focus();
        }
    }

    if (toggle) {
        toggle.addEventListener('click', function () {
            setSidebar(!sidebar.classList.contains('open'));
        });
    }
    if (backdrop) {
        backdrop.addEventListener('click', function () {
            setSidebar(false);
        });
    }
    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && sidebar && sidebar.classList.contains('open')) {
            setSidebar(false);
        }
    });

    // Collapse / expand the sidebar on desktop (remembered in this browser)
    var collapse = document.getElementById('sidebarCollapse');
    if (collapse) {
        var root = document.documentElement;
        var syncCollapseButton = function () {
            var collapsed = root.classList.contains('sidebar-collapsed');
            collapse.setAttribute('aria-pressed', collapsed ? 'true' : 'false');
            collapse.setAttribute('aria-label', collapsed ? 'Expand navigation menu' : 'Collapse navigation menu');
        };
        syncCollapseButton();
        collapse.addEventListener('click', function () {
            var collapsed = root.classList.toggle('sidebar-collapsed');
            try {
                window.localStorage.setItem('eodbSidebarCollapsed', collapsed ? '1' : '0');
            } catch (e) {
                /* storage unavailable: the choice applies to this page only */
            }
            syncCollapseButton();
        });
    }

    // Light / Dark Mode toggle (the theme is applied early by theme-init.js).
    // Switches without reloading, remembers the choice in this browser, and
    // tells the charts (reports.js) to redraw with matching colours.
    var themeRoot = document.documentElement;
    var themeToggle = document.getElementById('themeToggle');

    function currentTheme() {
        return themeRoot.getAttribute('data-theme') === 'dark' ? 'dark' : 'light';
    }

    function applyTheme(theme) {
        themeRoot.setAttribute('data-theme', theme);
        themeRoot.setAttribute('data-bs-theme', theme);
        if (themeToggle) {
            var label = theme === 'dark' ? 'Switch to light mode' : 'Switch to dark mode';
            themeToggle.setAttribute('aria-label', label);
            themeToggle.setAttribute('title', label);
        }
        document.dispatchEvent(new CustomEvent('eodb:themechange', { detail: { theme: theme } }));
    }

    function savedTheme() {
        try {
            return window.localStorage.getItem('eodbTheme');
        } catch (e) {
            return null;
        }
    }

    if (themeToggle) {
        applyTheme(currentTheme());
        themeToggle.addEventListener('click', function () {
            var next = currentTheme() === 'dark' ? 'light' : 'dark';
            applyTheme(next);
            try {
                window.localStorage.setItem('eodbTheme', next);
            } catch (e) {
                /* storage unavailable: the choice applies to this page only */
            }
        });

        // Follow the device setting only while the user has not chosen a theme
        if (window.matchMedia) {
            var systemDark = window.matchMedia('(prefers-color-scheme: dark)');
            var onSystemChange = function (event) {
                var saved = savedTheme();
                if (saved !== 'dark' && saved !== 'light') {
                    applyTheme(event.matches ? 'dark' : 'light');
                }
            };
            if (systemDark.addEventListener) {
                systemDark.addEventListener('change', onSystemChange);
            }
        }

        // Printing always uses the light theme (white paper), then switches back
        var themeBeforePrint = null;
        window.addEventListener('beforeprint', function () {
            themeBeforePrint = currentTheme();
            if (themeBeforePrint === 'dark') {
                applyTheme('light');
            }
        });
        window.addEventListener('afterprint', function () {
            if (themeBeforePrint === 'dark') {
                applyTheme('dark');
            }
            themeBeforePrint = null;
        });
    }

    // Confirmation prompts: <form data-confirm="Are you sure?">
    document.addEventListener('submit', function (event) {
        var form = event.target;
        var message = form.getAttribute('data-confirm');
        if (message && !window.confirm(message)) {
            event.preventDefault();
            return;
        }
        if (form.hasAttribute('data-once')) {
            if (form.dataset.submitted === '1') {
                event.preventDefault();
                return;
            }
            form.dataset.submitted = '1';
            var button = form.querySelector('button[type="submit"]');
            if (button) {
                button.disabled = true;
                button.classList.add('is-loading');
                button.setAttribute('aria-busy', 'true');
            }
        }
    });

    // Notification badge: refresh the unread count every 60 seconds.
    // The server treats this as a background request, so it does not keep an
    // idle session alive. Polling stops when the session has ended.
    var bell = document.getElementById('notificationButton');
    var badge = document.getElementById('notificationBadge');
    if (bell && badge && window.fetch) {
        var pollTimer = window.setInterval(function () {
            if (document.hidden) {
                return;
            }
            fetch(bell.getAttribute('data-fetch-url'), { credentials: 'same-origin', cache: 'no-store' })
                .then(function (response) {
                    if (response.status === 401) {
                        window.clearInterval(pollTimer);
                        return null;
                    }
                    return response.ok ? response.json() : null;
                })
                .then(function (data) {
                    if (!data) {
                        return;
                    }
                    var count = parseInt(data.unread, 10) || 0;
                    badge.textContent = count > 99 ? '99+' : String(count);
                    badge.hidden = count === 0;
                    bell.setAttribute('aria-label', 'Notifications (' + count + ' unread)');
                })
                .catch(function () { /* offline: try again next time */ });
        }, 60000);
    }

    // Password show/hide: <button data-toggle-password="#password">
    document.querySelectorAll('[data-toggle-password]').forEach(function (button) {
        button.addEventListener('click', function () {
            var field = document.querySelector(button.getAttribute('data-toggle-password'));
            if (!field) {
                return;
            }
            var show = field.type === 'password';
            field.type = show ? 'text' : 'password';
            button.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
            button.innerHTML = show
                ? '<i class="fa-solid fa-eye-slash"></i>'
                : '<i class="fa-solid fa-eye"></i>';
        });
    });
})();
