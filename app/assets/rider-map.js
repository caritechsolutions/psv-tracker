'use strict';

// Rider public live map. Consumes /api/public-positions.php (which carries NO
// driver name) + /api/public-routes.php + /api/ads.php. Marker reconciliation,
// server-clock "last seen", route filter, the rider's own GPS with the nearest
// van on the chosen route, and the ad banner rotation. No login, no driver
// identity.
(function () {
  var REFRESH_MS = 5000;
  var AD_ROTATE_MS = 6000;
  var BARBADOS = [13.19, -59.54];

  var map = L.map('map', { zoomControl: false }).setView(BARBADOS, 11);
  L.control.zoom({ position: 'topright' }).addTo(map);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 19,
    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
  }).addTo(map);
  setTimeout(function () { map.invalidateSize(); }, 0);

  var markers = {};                 // shift_id -> marker
  var latest = { vehicles: [], server_time: null };
  var timer = null, inFlight = false;
  var riderLoc = null;              // {lat,lng}
  var riderMarker = null;

  var elRoute = document.getElementById('route-filter');
  var elLocate = document.getElementById('locate-btn');
  var elNearest = document.getElementById('nearest');
  var elAd = document.getElementById('ad-banner');

  function esc(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }

  function parseSql(s) {
    var p = s.split(' '), d = p[0].split('-'), t = p[1].split(':');
    return new Date(+d[0], +d[1] - 1, +d[2], +t[0], +t[1], +t[2]);
  }
  function lastSeen(serverTime, receivedAt) {
    var secs = Math.max(0, Math.round((parseSql(serverTime) - parseSql(receivedAt)) / 1000));
    if (secs < 5) return 'just now';
    if (secs < 60) return secs + 's ago';
    return Math.floor(secs / 60) + 'm ' + (secs % 60) + 's ago';
  }
  function seatLabel(status) {
    if (status === 'available') return '<span class="seat-available">Seats available</span>';
    if (status === 'full') return '<span class="seat-full">Full</span>';
    return '<span class="seat-unknown">Seat status unknown</span>';
  }
  function ratingLabel(r) {
    if (!r || !r.count) return 'No ratings yet';
    return '<span class="popup-rating">' + (Math.round(r.avg * 10) / 10).toFixed(1) + ' ★ (' + r.count + ')</span>';
  }
  // Haversine distance in metres.
  function distM(a, b) {
    var R = 6371000, toRad = Math.PI / 180;
    var dLat = (b.lat - a.lat) * toRad, dLng = (b.lng - a.lng) * toRad;
    var la1 = a.lat * toRad, la2 = b.lat * toRad;
    var x = Math.sin(dLat / 2) * Math.sin(dLat / 2) +
            Math.cos(la1) * Math.cos(la2) * Math.sin(dLng / 2) * Math.sin(dLng / 2);
    return 2 * R * Math.asin(Math.sqrt(x));
  }
  function fmtDist(m) {
    return m < 1000 ? Math.round(m) + ' m' : (m / 1000).toFixed(1) + ' km';
  }

  // NOTE: popup intentionally omits any driver field — the public feed has none.
  function popupHtml(v, serverTime) {
    var speed = (v.speed == null) ? '—' : Math.round(v.speed) + ' km/h';
    var lines = [
      '<div class="popup-reg">' + esc(v.registration) + (v.label ? ' · ' + esc(v.label) : '') + '</div>',
      'Route ' + esc(v.route_number) + ' — ' + esc(v.route_name),
      seatLabel(v.seat_status),
      'Rating: ' + ratingLabel(v.rating),
      'Speed: ' + speed,
      'Last seen ' + esc(lastSeen(serverTime, v.received_at))
    ];
    return lines.join('<br>');
  }

  function routeMatches(v) {
    return !elRoute.value || String(v.route_number) === elRoute.value;
  }

  function render(data) {
    var seen = {};
    data.vehicles.forEach(function (v) {
      seen[v.shift_id] = true;
      var html = popupHtml(v, data.server_time);
      var m = markers[v.shift_id];
      if (m) {
        m.setLatLng([v.lat, v.lng]);
        if (m.getPopup()) m.setPopupContent(html); else m.bindPopup(html);
      } else {
        m = L.marker([v.lat, v.lng]).bindPopup(html);
        markers[v.shift_id] = m;
      }
      if (routeMatches(v)) { if (!map.hasLayer(m)) m.addTo(map); }
      else if (map.hasLayer(m)) map.removeLayer(m);
    });
    Object.keys(markers).forEach(function (id) {
      if (!seen[id]) { if (map.hasLayer(markers[id])) map.removeLayer(markers[id]); delete markers[id]; }
    });
    updateNearest();
  }

  function updateNearest() {
    if (!riderLoc) {
      elNearest.innerHTML = 'Pick a route and tap <strong>Near me</strong> to find your closest van.';
      return;
    }
    var shown = latest.vehicles.filter(routeMatches);
    if (!shown.length) {
      elNearest.innerHTML = elRoute.value
        ? 'No vans live on that route right now.'
        : 'No vans live right now.';
      return;
    }
    var best = null;
    shown.forEach(function (v) {
      var d = distM(riderLoc, { lat: v.lat, lng: v.lng });
      if (!best || d < best.d) best = { v: v, d: d };
    });
    elNearest.innerHTML = 'Nearest' + (elRoute.value ? ' on Route ' + esc(elRoute.value) : '') +
      ': <span class="reg">' + esc(best.v.registration) + '</span> · ' +
      '<span class="dist">' + fmtDist(best.d) + '</span> away · ' + seatLabel(best.v.seat_status);
  }

  elRoute.addEventListener('change', function () {
    // re-apply visibility immediately
    latest.vehicles.forEach(function (v) {
      var m = markers[v.shift_id];
      if (!m) return;
      if (routeMatches(v)) { if (!map.hasLayer(m)) m.addTo(map); }
      else if (map.hasLayer(m)) map.removeLayer(m);
    });
    updateNearest();
  });

  elLocate.addEventListener('click', function () {
    if (!navigator.geolocation) { elNearest.textContent = 'Location is not available on this device.'; return; }
    elNearest.textContent = 'Finding your location…';
    navigator.geolocation.getCurrentPosition(function (pos) {
      riderLoc = { lat: pos.coords.latitude, lng: pos.coords.longitude };
      if (riderMarker) { riderMarker.setLatLng([riderLoc.lat, riderLoc.lng]); }
      else {
        riderMarker = L.circleMarker([riderLoc.lat, riderLoc.lng], {
          radius: 8, color: '#0d4ea0', weight: 3, fillColor: '#4f9bff', fillOpacity: 1
        }).addTo(map).bindTooltip('You are here', { permanent: false });
      }
      map.setView([riderLoc.lat, riderLoc.lng], 14);
      updateNearest();
    }, function () {
      elNearest.textContent = 'Could not get your location (permission denied?).';
    }, { enableHighAccuracy: true, timeout: 10000, maximumAge: 30000 });
  });

  // ---- routes filter options ----
  function loadRoutes() {
    fetch('../api/public-routes.php', { headers: { Accept: 'application/json' } })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        if (!d || !d.routes) return;
        var cur = elRoute.value;
        var html = '<option value="">All routes</option>';
        d.routes.forEach(function (rt) {
          html += '<option value="' + esc(rt.route_number) + '">' +
                  esc(rt.route_number + ' — ' + rt.name) + '</option>';
        });
        elRoute.innerHTML = html;
        elRoute.value = cur;
      })
      .catch(function () {});
  }

  // ---- positions poll ----
  function schedule() {
    clearTimeout(timer);
    if (document.visibilityState === 'visible') timer = setTimeout(poll, REFRESH_MS);
  }
  function poll() {
    if (inFlight) return;
    inFlight = true;
    fetch('../api/public-positions.php', { headers: { Accept: 'application/json' } })
      .then(function (r) { if (!r.ok) throw new Error('HTTP ' + r.status); return r.json(); })
      .then(function (d) { if (d) { latest = d; render(d); } })
      .catch(function () {})
      .then(function () { inFlight = false; schedule(); });
  }
  document.addEventListener('visibilitychange', function () {
    clearTimeout(timer);
    if (document.visibilityState === 'visible') poll();
  });

  // ---- ad banner ----
  function loadAds() {
    fetch('../api/ads.php', { headers: { Accept: 'application/json' } })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        var ads = (d && d.ads) || [];
        if (!ads.length) { elAd.hidden = true; return; }
        elAd.hidden = false;
        var i = 0;
        function show() {
          var ad = ads[i % ads.length];
          var img = '<img src="' + esc(ad.image_url) + '" alt="' + esc(ad.alt || '') + '">';
          var inner = ad.click_url
            ? '<a href="' + esc(ad.click_url) + '" target="_blank" rel="noopener">' + img + '</a>'
            : img;
          elAd.innerHTML = '<span class="ad-tag">Ad</span>' + inner;
          i++;
        }
        show();
        if (ads.length > 1) setInterval(show, AD_ROTATE_MS);
      })
      .catch(function () { elAd.hidden = true; });
  }

  loadRoutes();
  loadAds();
  poll();
})();
