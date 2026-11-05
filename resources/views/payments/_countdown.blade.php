{{-- M3b PAY-12/PAY-13/PAY-35. Shared countdown: server sends expires_at
     and server_now (ISO-8601 UTC); the client corrects for its own
     clock skew. The visible countdown ticks every second (every 5s
     with prefers-reduced-motion); the aria-live announcement only
     changes when the minute changes, so assistive tech is not spammed.
     At zero the client NEVER decides the status itself: it asks the
     server (pollUrl, when given) or reloads, and the server expires
     the order on access first. pollUrl is polled every 3s only while
     the tab is visible; a final status redirects and stops polling. --}}
<script>
(function () {
    var el = document.getElementById('countdown');
    if (!el) return;
    var expires = new Date(el.dataset.expiresAt).getTime();
    var serverNow = new Date(el.dataset.serverNow).getTime();
    var offset = Date.now() - serverNow;
    var aria = document.getElementById('countdown-aria');
    var pollUrl = el.dataset.pollUrl || '';
    var lastMinute = null;
    var reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var step = reduced ? 5000 : 1000;

    function fmt(ms) {
        if (ms <= 0) return '00:00:00';
        var s = Math.floor(ms / 1000);
        var h = String(Math.floor(s / 3600)).padStart(2, '0');
        var m = String(Math.floor((s % 3600) / 60)).padStart(2, '0');
        var sec = String(s % 60).padStart(2, '0');
        return h + ':' + m + ':' + sec;
    }

    function confirmFromServer() {
        if (pollUrl) {
            fetch(pollUrl, {headers: {'Accept': 'application/json'}})
                .then(function (r) { return r.json(); })
                .then(function (d) {
                    if (d.redirect_url) { window.location.href = d.redirect_url; }
                    else { window.location.reload(); }
                })
                .catch(function () { window.location.reload(); });
        } else {
            window.location.reload();
        }
    }

    function tick() {
        var remaining = expires - (Date.now() - offset);
        if (remaining <= 0) {
            el.textContent = '00:00:00';
            if (aria) aria.textContent = 'Waktu bayar habis.';
            confirmFromServer();
            return;
        }
        el.textContent = fmt(remaining);
        var minute = Math.floor(remaining / 60000);
        if (aria && minute !== lastMinute) {
            lastMinute = minute;
            aria.textContent = 'Sisa waktu bayar ' + fmt(remaining) + '.';
        }
    }

    tick();
    var timer = setInterval(tick, step);

    if (pollUrl) {
        var poller = setInterval(function () {
            if (document.visibilityState !== 'visible') return;
            fetch(pollUrl, {headers: {'Accept': 'application/json'}})
                .then(function (r) { return r.json(); })
                .then(function (d) {
                    if (d.redirect_url) {
                        clearInterval(poller);
                        clearInterval(timer);
                        window.location.href = d.redirect_url;
                    }
                })
                .catch(function () {});
        }, 3000);
    }
})();
</script>
