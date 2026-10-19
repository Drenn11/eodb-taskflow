/**
 * EODB-TaskFlow - Update Task form
 * Keeps the status buttons, slider, and progress box in agreement
 * (Pending = 0%, In Progress = 1-99%, Completed = 100%) and previews the
 * status that will be saved (Algorithm 3). The server checks everything again.
 */
(function () {
    'use strict';

    var form = document.getElementById('updateTaskForm');
    if (!form) {
        return;
    }

    var range = document.getElementById('progressRange');
    var number = document.getElementById('progress');
    var preview = document.getElementById('statusPreview');
    var deadlinePassed = preview.getAttribute('data-deadline-passed') === '1';
    var radios = form.querySelectorAll('input[name="status"]');

    function clamp(value) {
        value = parseInt(value, 10);
        if (isNaN(value)) {
            return 0;
        }
        return Math.max(0, Math.min(100, value));
    }

    function setStatus(status) {
        radios.forEach(function (radio) {
            radio.checked = radio.value === status;
        });
    }

    function statusFor(progress) {
        if (progress >= 100) {
            return 'Completed';
        }
        return progress > 0 ? 'In Progress' : 'Pending';
    }

    function updatePreview(progress) {
        var saved = progress >= 100 ? 'Completed' : (deadlinePassed ? 'Overdue' : statusFor(progress));
        preview.textContent = saved + ' (' + progress + '%)';
    }

    function setProgress(progress, fromStatus) {
        progress = clamp(progress);
        number.value = progress;
        range.value = progress;
        if (!fromStatus) {
            setStatus(statusFor(progress));
        }
        updatePreview(progress);
    }

    range.addEventListener('input', function () {
        setProgress(range.value, false);
    });
    number.addEventListener('input', function () {
        setProgress(number.value, false);
    });

    radios.forEach(function (radio) {
        radio.addEventListener('change', function () {
            var current = clamp(number.value);
            if (radio.value === 'Pending') {
                setProgress(0, true);
            } else if (radio.value === 'Completed') {
                setProgress(100, true);
                document.getElementById('remarks').focus();
            } else if (current === 0 || current === 100) {
                setProgress(current === 100 ? 90 : 10, true);
            } else {
                updatePreview(current);
            }
        });
    });

    updatePreview(clamp(number.value));
})();
