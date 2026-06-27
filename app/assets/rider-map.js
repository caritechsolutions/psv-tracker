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

  function speedText(s) { return (s == null) ? '—' : Math.round(s) + ' km/h'; }

  // NOTE: popup intentionally omits any driver field — the public feed has none.
  // Dynamic fields are wrapped in hooks (.pp-seat/.pp-rating/.pp-speed/
  // .pp-lastseen) so the poll can refresh them in place without rebuilding the
  // popup (which would wipe an in-progress check-in). Static parts (registration,
  // route) and the check-in area are left alone on refresh.
  function popupHtml(v, serverTime) {
    return '<div class="popup-reg">' + esc(v.registration) + (v.label ? ' · ' + esc(v.label) : '') + '</div>' +
      'Route ' + esc(v.route_number) + ' — ' + esc(v.route_name) + '<br>' +
      '<span class="pp-seat">' + seatLabel(v.seat_status) + '</span><br>' +
      'Rating: <span class="pp-rating">' + ratingLabel(v.rating) + '</span><br>' +
      'Speed: <span class="pp-speed">' + esc(speedText(v.speed)) + '</span><br>' +
      '<span class="pp-status">Last seen <span class="pp-lastseen">' + esc(lastSeen(serverTime, v.received_at)) + '</span></span>' +
      checkinBlock(v);
  }

  function setHtml(el, sel, html) { var n = el.querySelector(sel); if (n) n.innerHTML = html; }
  function setText(el, sel, txt) { var n = el.querySelector(sel); if (n) n.textContent = txt; }

  // Refresh only the live fields of an OPEN popup, in place. Does not touch the
  // check-in area or rebuild the popup, so an in-progress check-in is preserved.
  function updateOpenPopup(m, v, serverTime) {
    var el = m.getPopup() && m.getPopup().getElement();
    if (!el) return;
    m._gone = false;
    setHtml(el, '.pp-seat', seatLabel(v.seat_status));
    setHtml(el, '.pp-rating', ratingLabel(v.rating));
    setText(el, '.pp-speed', speedText(v.speed));
    var status = el.querySelector('.pp-status');
    if (status) {
      var ls = status.querySelector('.pp-lastseen');
      if (ls) ls.textContent = lastSeen(serverTime, v.received_at);
      else status.innerHTML = 'Last seen <span class="pp-lastseen">' + esc(lastSeen(serverTime, v.received_at)) + '</span>';
    }
    // If it had gone stale and came back, re-enable a check-in button we disabled
    // (but never a button the rider already used — that one stays as is).
    var btn = el.querySelector('.checkin-btn[data-stale]');
    if (btn) { btn.disabled = false; btn.removeAttribute('data-stale'); btn.textContent = 'Check in'; }
  }

  // The vehicle dropped out of the feed while its popup is open: show "no longer
  // reporting" instead of a frozen stale value, and keep the marker until the
  // rider closes the popup (then popupclose removes it).
  function markStale(m) {
    m._gone = true;
    var el = m.getPopup() && m.getPopup().getElement();
    if (!el) return;
    var status = el.querySelector('.pp-status');
    if (status) status.innerHTML = '<span class="pp-gone">No longer reporting</span>';
    var btn = el.querySelector('.checkin-btn');
    if (btn && !btn.disabled) { btn.disabled = true; btn.setAttribute('data-stale', '1'); btn.textContent = 'Unavailable'; }
  }

  function checkinBlock(v) {
    if (window.RIDER_LOGGED_IN) {
      return '<div class="checkin-area"><button class="checkin-btn" data-shift="' + v.shift_id + '">Check in</button></div>';
    }
    return '<div class="checkin-area"><a class="checkin-signin" href="login.php">Sign in to check in &amp; earn points</a></div>';
  }

  // ---- toast ----
  var toastEl = document.getElementById('toast');
  var toastTimer = null;
  function showToast(msg, kind) {
    if (!toastEl) return;
    toastEl.textContent = msg;
    toastEl.className = 'toast ' + (kind === 'ok' ? 'toast-ok' : 'toast-err');
    toastEl.hidden = false;
    clearTimeout(toastTimer);
    toastTimer = setTimeout(function () { toastEl.hidden = true; }, 5000);
  }

  // ---- check-in (logged-in riders) ----
  function handleCheckin(res, btn, orig) {
    var d = res.d || {};
    if (res.status === 401) { showToast('Please sign in to check in.', 'err'); btn.disabled = false; btn.textContent = orig; return; }
    if (d.ok && d.status === 'checked_in') { showToast('Checked in! +1 point — you have ' + d.points + '.', 'ok'); btn.textContent = 'Checked in ✓'; return; }
    if (d.ok && d.status === 'already_checked_in') { showToast("You're already checked in to this van.", 'ok'); btn.textContent = 'Checked in ✓'; return; }
    if (d.error === 'too_far') { showToast('You need to be near the van to check in' + (d.distance_m ? ' (you are about ' + d.distance_m + ' m away).' : '.'), 'err'); btn.disabled = false; btn.textContent = orig; return; }
    if (d.error === 'shift_unavailable') { showToast("This van isn't available to check into right now.", 'err'); btn.disabled = false; btn.textContent = orig; return; }
    showToast('Check-in failed. Please try again.', 'err'); btn.disabled = false; btn.textContent = orig;
  }

  function doCheckin(btn) {
    var shiftId = btn.getAttribute('data-shift');
    if (btn.disabled || !shiftId) return;
    if (!navigator.geolocation) { showToast('Location is not available on this device.', 'err'); return; }
    var orig = btn.textContent;
    btn.disabled = true; btn.textContent = 'Checking in…';
    navigator.geolocation.getCurrentPosition(function (pos) {
      var body = new URLSearchParams({
        csrf: window.RIDER_CSRF || '', shift_id: shiftId,
        lat: pos.coords.latitude, lng: pos.coords.longitude
      });
      fetch('checkin.php', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: body, credentials: 'same-origin' })
        .then(function (r) { return r.json().then(function (d) { return { status: r.status, d: d }; }); })
        .then(function (res) { handleCheckin(res, btn, orig); })
        .catch(function () { showToast('Check-in failed. Please try again.', 'err'); btn.disabled = false; btn.textContent = orig; });
    }, function () {
      showToast('Could not get your location (permission denied?).', 'err');
      btn.disabled = false; btn.textContent = orig;
    }, { enableHighAccuracy: true, timeout: 10000, maximumAge: 10000 });
  }

  document.addEventListener('click', function (e) {
    var btn = e.target.closest && e.target.closest('.checkin-btn');
    if (btn) doCheckin(btn);
  });

  function routeMatches(v) {
    return !elRoute.value || String(v.route_number) === elRoute.value;
  }

  function render(data) {
    var seen = {};
    data.vehicles.forEach(function (v) {
      seen[v.shift_id] = true;
      var m = markers[v.shift_id];
      if (m) {
        m.setLatLng([v.lat, v.lng]);   // marker still moves
        if (m.getPopup()) {
          // Open popup: refresh live fields in place (keeps check-in state).
          // Closed popup: rebuild so the next open is fresh.
          if (m.isPopupOpen()) updateOpenPopup(m, v, data.server_time);
          else m.setPopupContent(popupHtml(v, data.server_time));
        } else {
          m.bindPopup(popupHtml(v, data.server_time));
        }
      } else {
        m = L.marker([v.lat, v.lng]).bindPopup(popupHtml(v, data.server_time));
        m._shift = v.shift_id;
        markers[v.shift_id] = m;
      }
      if (routeMatches(v)) { if (!map.hasLayer(m)) m.addTo(map); }
      else if (map.hasLayer(m)) map.removeLayer(m);
    });
    Object.keys(markers).forEach(function (id) {
      if (seen[id]) return;
      var m = markers[id];
      if (m.getPopup() && m.isPopupOpen()) {
        markStale(m);   // keep it open, but flag it; removed on popupclose
      } else {
        if (map.hasLayer(m)) map.removeLayer(m);
        delete markers[id];
      }
    });
    updateNearest();
  }

  // Remove a vehicle that went stale once the rider closes its popup.
  map.on('popupclose', function (e) {
    var m = e.popup && e.popup._source;
    if (m && m._gone) {
      if (map.hasLayer(m)) map.removeLayer(m);
      if (m._shift != null) delete markers[m._shift];
    }
  });

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
