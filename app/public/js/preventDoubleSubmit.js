document.addEventListener('submit', function (e) {
    if (e.defaultPrevented) return;

    var form = e.target;
    if (!form || form.tagName !== 'FORM') return;

    var buttons = form.querySelectorAll('button[type="submit"], input[type="submit"]');
    if (!buttons.length) return;

    var submitter = e.submitter;
    if (submitter && submitter.name) {
        var clone = document.createElement('input');
        clone.type = 'hidden';
        clone.name = submitter.name;
        clone.value = submitter.value;
        form.appendChild(clone);
        setTimeout(function () {
            clone.remove();
        }, 1000);
    }

    for (var i = 0; i < buttons.length; i++) {
        buttons[i].disabled = true;
    }
});