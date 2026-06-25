'use strict';

// Live map for the admin portal. Polls /admin/positions.php and plots one
// marker per currently signed-on vehicle. "Last seen" is computed from the
// server clock (server_time vs received_at) returned by the endpoint, never
// the browser clock.
(function () {
  var REFRESH_MS = 5000;
  var BARBADOS = [13.19, -59.54];

  var map = L.map('map').setView(BARBADOS, 11);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 19,
    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
  }).addTo(map);

  // shift_id -> Leaflet marker, so refreshes move/keep markers instead of
  // tearing them down and rebuilding (no flicker, popups stay put).
  var markers = {};
  var timer = null;
  var inFlight = false;

  function esc(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }

  // 'YYYY-MM-DD HH:MM:SS' -> Date. Both timestamps come from the same DB
  // clock, so parsing each the same way makes their difference correct
  // regardless of the viewer's timezone.
  function parseSql(s) {
    var p = s.split(' ');
    var d = p[0].split('-');
    var t = p[1].split(':');
    return new Date(+d[0], +d[1] - 1, +d[2], +t[0], +t[1], +t[2]);
  }

  function lastSeen(serverTime, receivedAt) {
    var secs = Math.max(0, Math.round((parseSql(serverTime) - parseSql(receivedAt)) / 1000));
    if (secs < 5) return 'just now';
    if (secs < 60) return secs + 's ago';
    var m = Math.floor(secs / 60);
    return m + 'm ' + (secs % 60) + 's ago';
  }

  function seatLabel(status) {
    if (status === 'available') return '<span class="seat-available">Seats available</span>';
    if (status === 'full') return '<span class="seat-full">Full</span>';
    return '<span class="seat-unknown">Seat status unknown</span>';
  }

  function popupHtml(v, serverTime) {
    var speed = (v.speed === null || v.speed === undefined) ? '—' : Math.round(v.speed) + ' km/h';
    return [
      '<div class="popup-reg">' + esc(v.registration) + '</div>',
      'Route ' + esc(v.route_number) + ' — ' + esc(v.route_name),
      seatLabel(v.seat_status),
      'Speed: ' + speed,
      'Last seen ' + esc(lastSeen(serverTime, v.received_at))
    ].join('<br>');
  }

  function render(data) {
    var seen = {};
    data.vehicles.forEach(function (v) {
      seen[v.shift_id] = true;
      var html = popupHtml(v, data.server_time);
      var m = markers[v.shift_id];
      if (m) {
        m.setLatLng([v.lat, v.lng]);
        if (m.getPopup()) { m.setPopupContent(html); } else { m.bindPopup(html); }
      } else {
        m = L.marker([v.lat, v.lng]).addTo(map).bindPopup(html);
        markers[v.shift_id] = m;
      }
    });
    // Drop vehicles that fell out of the freshness window / signed off.
    Object.keys(markers).forEach(function (id) {
      if (!seen[id]) { map.removeLayer(markers[id]); delete markers[id]; }
    });
  }

  function setStatus(text) {
    var el = document.getElementById('status');
    if (el) el.textContent = text;
  }

  function schedule() {
    clearTimeout(timer);
    if (document.visibilityState === 'visible') {
      timer = setTimeout(poll, REFRESH_MS);
    }
  }

  function poll() {
    if (inFlight) return;
    inFlight = true;
    fetch('positions.php', { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
      .then(function (res) {
        if (res.status === 401) { window.location = 'login.php'; return null; }
        if (!res.ok) throw new Error('HTTP ' + res.status);
        return res.json();
      })
      .then(function (data) {
        if (!data) return;
        render(data);
        var n = data.vehicles.length;
        setStatus(n + ' vehicle' + (n === 1 ? '' : 's') + ' live · updated ' + data.server_time.split(' ')[1]);
      })
      .catch(function () {
        setStatus('Connection problem — retrying…');
      })
      .then(function () {
        inFlight = false;
        schedule();
      });
  }

  // Chain via setTimeout off each completed response (not setInterval) so a
  // slow response can't let requests stack up. Pause entirely when the tab is
  // hidden, and refresh immediately when it comes back into view.
  document.addEventListener('visibilitychange', function () {
    clearTimeout(timer);
    if (document.visibilityState === 'visible') poll();
  });

  poll();
})();
