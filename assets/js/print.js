/**
 * EODB-TaskFlow - Printable report page
 * The "Print / Save as PDF" button opens the browser print dialog.
 */
(function () {
    'use strict';
    var button = document.getElementById('printButton');
    if (button) {
        button.addEventListener('click', function () {
            window.print();
        });
    }
})();
