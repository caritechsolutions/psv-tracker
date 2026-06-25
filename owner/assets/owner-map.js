'use strict';

// Owner portal live map. Adapted from admin/assets/map.js: same tile layer,
// marker reconciliation by shift_id, server-clock "last seen", setTimeout-chained
// poll, and visibilitychange pause — trimmed to a map + simple vehicle list (no
// filters; the feed is already scoped to this owner server-side). A 401 from the
// poll (expired session) sends the user back to the owner login.
(function () {
  var REFRESH_MS = 5000;
  var BARBADOS = [13.19, -59.54];

  var map = L.map('map', { zoomControl: false }).setView(BARBADOS, 11);
  L.control.zoom({ position: 'topright' }).addTo(map);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 19,
    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
  }).addTo(map);
  setTimeout(function () { map.invalidateSize(); }, 0);

  var markers = {};
  var latest = { vehicles: [], server_time: null };
  var timer = null;
  var inFlight = false;

  var elStatus = document.getElementById('status');
  var elList = document.getElementById('vehicle-list');

  function esc(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }

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

  function seatShort(status) {
    if (status === 'available') return '<span class="seat-available">Available</span>';
    if (status === 'full') return '<span class="seat-full">Full</span>';
    return '<span class="seat-unknown">Unknown</span>';
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
    Object.keys(markers).forEach(function (id) {
      if (!seen[id]) { map.removeLayer(markers[id]); delete markers[id]; }
    });

    var n = data.vehicles.length;
    if (elStatus) elStatus.textContent = n + ' live';
    renderList(data);
  }

  function renderList(data) {
    if (!elList) return;
    if (data.vehicles.length === 0) {
      elList.innerHTML = '<li class="empty">No vehicles live right now.</li>';
      return;
    }
    elList.innerHTML = data.vehicles.map(function (v) {
      return '<li data-shift="' + esc(v.shift_id) + '">' +
        '<span class="reg">' + esc(v.registration) + '</span>' +
        '<span class="meta">Route ' + esc(v.route_number) + ' · ' + seatShort(v.seat_status) +
        ' · ' + esc(lastSeen(data.server_time, v.received_at)) + '</span></li>';
    }).join('');
  }

  if (elList) {
    elList.addEventListener('click', function (e) {
      var li = e.target.closest('li[data-shift]');
      if (!li) return;
      var id = li.getAttribute('data-shift');
      var v = null;
      latest.vehicles.forEach(function (x) { if (String(x.shift_id) === id) v = x; });
      if (!v) return;
      map.setView([v.lat, v.lng], 15, { animate: true });
      var m = markers[v.shift_id];
      if (m) m.openPopup();
    });
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
        latest = data;
        render(data);
      })
      .catch(function () {
        if (elStatus) elStatus.textContent = 'Connection problem…';
      })
      .then(function () {
        inFlight = false;
        schedule();
      });
  }

  document.addEventListener('visibilitychange', function () {
    clearTimeout(timer);
    if (document.visibilityState === 'visible') poll();
  });

  poll();
})();
