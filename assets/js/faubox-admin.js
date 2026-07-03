(function () {
    if (!window.fauboxAdmin) return;

    var knownTime  = fauboxAdmin.knownTime;
    var restUrl    = fauboxAdmin.restUrl;
    var nonce      = fauboxAdmin.nonce;
    var buildingEl =
        document.getElementById('faubox-building-notice');
    var statusEl   = document.getElementById('faubox-index-status');
    var refreshForm = document.getElementById('faubox-index-refresh-form');
    var refreshProgress = document.getElementById('faubox-refresh-progress');
    var refreshButton = refreshForm
        ? refreshForm.querySelector('input[type="submit"], button[type="submit"]')
        : null;
    var isRefreshing = false;

    function showProgress() {
        if (!refreshProgress) return;

        refreshProgress.hidden = false;
        refreshProgress.classList.add('is-active');
    }

    function updateStatus(data) {
        if (!statusEl || !data.built) return;

        var d = new Date(data.time * 1000);
        var text = fauboxAdmin.labelLastBuild + ': '
            + d.toLocaleDateString() + ' '
            + d.toLocaleTimeString()
            + ' · ' + data.count + ' '
            + fauboxAdmin.labelFolders;

        statusEl.textContent = '';
        var strong = document.createElement('strong');
        strong.textContent = text;
        statusEl.appendChild(strong);
    }

    function fetchStatus() {
        return fetch(restUrl, { headers: { 'X-WP-Nonce': nonce } })
            .then(function (r) { return r.json(); });
    }

    function pollAfterSettingsSave() {
        if (!fauboxAdmin.polling) return;

        var poll = setInterval(function () {
            fetchStatus().then(function (data) {
                if (data.built && data.time !== knownTime) {
                    clearInterval(poll);
                    if (buildingEl) {
                        buildingEl.style.display = 'none';
                    }
                    updateStatus(data);
                }
            });
        }, 3000);

        setTimeout(function () { clearInterval(poll); }, 300000);
    }

    function handleManualRefresh() {
        if (!refreshForm || isRefreshing) return;

        isRefreshing = true;

        if (refreshButton) {
            refreshButton.disabled = true;
            refreshButton.classList.add('disabled');
        }
        showProgress();
    }

    if (refreshForm) {
        refreshForm.addEventListener('submit', handleManualRefresh);
    }

    pollAfterSettingsSave();
}());
