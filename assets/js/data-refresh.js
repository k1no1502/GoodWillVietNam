(function () {
    var script = document.currentScript;
    if (!script) {
        return;
    }

    var basePath = script.getAttribute('data-base') || '';
    var intervalAttr = parseInt(script.getAttribute('data-interval') || '5000', 10);
    var pollInterval = isNaN(intervalAttr) ? 5000 : Math.max(2000, intervalAttr);
    var endpoint = basePath + 'api/get-data-version.php';
    var lastVersion = null;
    var timer = null;

    function checkVersion() {
        fetch(endpoint, { cache: 'no-store' })
            .then(function (response) {
                return response.json();
            })
            .then(function (data) {
                if (!data.success) {
                    return;
                }

                if (lastVersion && lastVersion !== data.version) {
                    window.location.reload();
                    return;
                }

                lastVersion = data.version;
            })
            .catch(function (err) {
                console.error('Auto-refresh check failed:', err);
            });
    }

    function startPolling() {
        if (timer) {
            clearInterval(timer);
        }
        checkVersion();
        timer = setInterval(checkVersion, pollInterval);
    }

    document.addEventListener('visibilitychange', function () {
        if (document.hidden) {
            if (timer) {
                clearInterval(timer);
                timer = null;
            }
        } else {
            startPolling();
        }
    });

    startPolling();
})();
