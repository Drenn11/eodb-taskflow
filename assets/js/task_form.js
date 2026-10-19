/**
 * EODB-TaskFlow - Create Task form
 * Shows the "Initial Progress" field only when the initial status is In Progress.
 */
(function () {
    'use strict';

    var status = document.getElementById('initial_status');
    var group = document.getElementById('initialProgressGroup');
    var progress = document.getElementById('initial_progress');

    if (!status || !group || !progress) {
        return;
    }

    function sync() {
        var inProgress = status.value === 'In Progress';
        group.hidden = !inProgress;
        progress.required = inProgress;
        if (inProgress && progress.value === '') {
            progress.value = '10';
        }
    }

    status.addEventListener('change', sync);
    sync();
})();
