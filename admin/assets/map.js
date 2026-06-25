'use strict';

// Live map for the admin portal. Polls /admin/positions.php and plots one
// marker per currently signed-on vehicle, with a collapsible control panel
// (search + route/seat filters + clickable vehicle list). "Last seen" is
// computed from the server clock (server_time vs received_at) the endpoint
// returns, never the browser clock.
(function () {
  var REFRESH_MS = 5000;
  var BARBADOS = [13.19, -59.54];

  // Zoom control on the right so it doesn't sit under the top-left panel.
  var map = L.map('map', { zoomControl: false }).setView(BARBADOS, 11);
  L.control.zoom({ position: 'topright' }).addTo(map);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 19,
    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
  }).addTo(map);

  // The map container is sized by the flex shell, so make Leaflet re-measure.
  setTimeout(function () { map.invalidateSize(); }, 0);

  // shift_id -> Leaflet marker. Markers are created here but added/removed from
  // the map by applyView() according to the active filters.
  var markers = {};
  var latest = { vehicles: [], server_time: null };
  // route_number -> route_name, accumulated across polls so a route the user
  // has selected stays in the dropdown even when its bus goes temporarily
  // quiet (no live vehicle in the current payload).
  var knownRoutes = {};
  var timer = null;
  var inFlight = false;

  var elStatus  = document.getElementById('status');
  var elCount   = document.getElementById('panel-count');
  var elList    = document.getElementById('vehicle-list');
  var elSearch  = document.getElementById('flt-search');
  var elRoute   = document.getElementById('flt-route');
  var elSeat    = document.getElementById('flt-seat');
  var elPanel   = document.getElementById('panel');
  var elToggle  = document.getElementById('panel-toggle');

  function esc(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }

  // 'YYYY-MM-DD HH:MM:SS' -> Date. Both timestamps come from the same DB clock,
  // so parsing each the same way makes their difference correct regardless of
  // the viewer's timezone.
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

  // Create/update/remove marker objects to mirror the payload. Does NOT decide
  // map membership — applyView() does, based on filters.
  function syncMarkers(data) {
    var seen = {};
    data.vehicles.forEach(function (v) {
      seen[v.shift_id] = true;
      var html = popupHtml(v, data.server_time);
      var m = markers[v.shift_id];
      if (m) {
        m.setLatLng([v.lat, v.lng]);
        if (m.getPopup()) { m.setPopupContent(html); } else { m.bindPopup(html); }
      } else {
        m = L.marker([v.lat, v.lng]).bindPopup(html);
        markers[v.shift_id] = m;
      }
    });
    Object.keys(markers).forEach(function (id) {
      if (!seen[id]) {
        if (map.hasLayer(markers[id])) map.removeLayer(markers[id]);
        delete markers[id];
      }
    });
  }

  function refreshRouteOptions(vehicles) {
    vehicles.forEach(function (v) {
      if (v.route_number) knownRoutes[v.route_number] = v.route_name;
    });
    var cur = elRoute.value;
    var keys = Object.keys(knownRoutes).sort(function (a, b) {
      return a.localeCompare(b, undefined, { numeric: true });
    });
    var html = '<option value="">All routes</option>';
    keys.forEach(function (k) {
      html += '<option value="' + esc(k) + '">' + esc(k + ' — ' + knownRoutes[k]) + '</option>';
    });
    elRoute.innerHTML = html;
    // knownRoutes only grows, so a previously-selected route is still an option
    // here — the selection survives a bus going quiet. (Falls back to All only
    // if the value somehow isn't present.)
    elRoute.value = cur;
    if (elRoute.value !== cur) elRoute.value = '';
  }

  function matches(v) {
    if (elRoute.value && v.route_number !== elRoute.value) return false;
    if (elSeat.value && v.seat_status !== elSeat.value) return false;
    var q = elSearch.value.trim().toLowerCase();
    if (q) {
      var hay = (v.registration + ' ' + v.route_number + ' ' + v.route_name).toLowerCase();
      if (hay.indexOf(q) < 0) return false;
    }
    return true;
  }

  function renderList(rows, serverTime) {
    if (rows.length === 0) {
      elList.innerHTML = '<li class="empty">No matching vehicles</li>';
      return;
    }
    elList.innerHTML = rows.map(function (v) {
      return '<li data-shift="' + esc(v.shift_id) + '">' +
        '<span class="reg">' + esc(v.registration) + '</span>' +
        '<span class="meta">Route ' + esc(v.route_number) + ' · ' + seatShort(v.seat_status) +
        ' · ' + esc(lastSeen(serverTime, v.received_at)) + '</span></li>';
    }).join('');
  }

  // Apply the current filters: toggle marker map-membership, rebuild the list,
  // and update the counts. Called by the poll and by every filter change, so
  // filtering is instant and doesn't wait for the next refresh.
  function applyView() {
    var total = latest.vehicles.length;
    var rows = [];
    latest.vehicles.forEach(function (v) {
      var ok = matches(v);
      var m = markers[v.shift_id];
      if (m) {
        if (ok && !map.hasLayer(m)) m.addTo(map);
        else if (!ok && map.hasLayer(m)) map.removeLayer(m);
      }
      if (ok) rows.push(v);
    });
    renderList(rows, latest.server_time);

    var shown = rows.length;
    if (elStatus) {
      if (total === 0) elStatus.textContent = 'No vehicles live';
      else if (shown === total) elStatus.textContent = total + ' vehicle' + (total === 1 ? '' : 's') + ' live';
      else elStatus.textContent = shown + ' of ' + total + ' shown';
    }
    if (elCount) elCount.textContent = shown === total ? String(total) : shown + '/' + total;
  }

  // List row -> pan/zoom to that vehicle and open its popup.
  elList.addEventListener('click', function (e) {
    var li = e.target.closest('li[data-shift]');
    if (!li) return;
    var id = li.getAttribute('data-shift');
    var v = null;
    latest.vehicles.forEach(function (x) { if (String(x.shift_id) === id) v = x; });
    if (!v) return;
    map.setView([v.lat, v.lng], 15, { animate: true });
    var m = markers[v.shift_id];
    if (m) {
      if (!map.hasLayer(m)) m.addTo(map);
      m.openPopup();
    }
  });

  [elSearch, elRoute, elSeat].forEach(function (el) {
    el.addEventListener('input', applyView);
    el.addEventListener('change', applyView);
  });

  elToggle.addEventListener('click', function () {
    var collapsed = elPanel.classList.toggle('collapsed');
    var icon = elToggle.querySelector('i');
    if (icon) icon.className = 'ti ' + (collapsed ? 'ti-chevron-right' : 'ti-chevron-left');
    elToggle.setAttribute('aria-label', collapsed ? 'Expand panel' : 'Collapse panel');
    elToggle.setAttribute('title', collapsed ? 'Expand panel' : 'Collapse panel');
  });

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
        refreshRouteOptions(data.vehicles);
        syncMarkers(data);
        applyView();
      })
      .catch(function () {
        if (elStatus) elStatus.textContent = 'Connection problem — retrying…';
      })
      .then(function () {
        inFlight = false;
        schedule();
      });
  }

  // Chain via setTimeout off each completed response (not setInterval) so a slow
  // response can't let requests stack up. Pause entirely when the tab is hidden,
  // and refresh immediately when it comes back into view.
  document.addEventListener('visibilitychange', function () {
    clearTimeout(timer);
    if (document.visibilityState === 'visible') poll();
  });

  poll();
})();
