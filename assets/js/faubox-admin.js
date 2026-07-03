(function () {
    if (!window.fauboxAdmin || !fauboxAdmin.polling) return;

    var knownTime  = fauboxAdmin.knownTime;
    var restUrl    = fauboxAdmin.restUrl;
    var nonce      = fauboxAdmin.nonce;
    var buildingEl =
        document.getElementById('faubox-building-notice');
    var statusEl   = document.getElementById('faubox-index-status');

    var poll = setInterval(function () {
        fetch(restUrl, { headers: { 'X-WP-Nonce': nonce } })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.built && data.time !== knownTime) {
                    clearInterval(poll);
                    if (buildingEl) buildingEl.style.display =
                        'none';
                    if (statusEl) {
                        var d = new Date(data.time * 1000);
                        statusEl.textContent =
                            fauboxAdmin.labelLastBuild + ': '
                            + d.toLocaleDateString() + ' ' +
                            d.toLocaleTimeString()
                            + ' · ' + data.count + ' ' +
                            fauboxAdmin.labelFolders;
                    }
                }
            });
    }, 3000);

    setTimeout(function () { clearInterval(poll); }, 300000);
}());
