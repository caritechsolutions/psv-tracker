<?php
declare(strict_types=1);
require __DIR__ . '/partials/layout.php';

// Public live map — no login required.
rider_layout_start([
    'title' => 'PSV Tracker — catch your van',
    'head'  => RIDER_LEAFLET_CSS,
]);
?>
<div class="rider-map-wrap">
  <div id="map"></div>

  <div class="rider-controls">
    <div class="control-row">
      <select id="route-filter" aria-label="Filter by route">
        <option value="">All routes</option>
      </select>
      <button id="locate-btn" type="button">Near me</button>
    </div>
    <div id="nearest" class="nearest">Pick a route and tap <strong>Near me</strong> to find your closest van.</div>
  </div>
</div>

<div id="ad-banner" class="ad-banner" hidden></div>
<?php
rider_layout_end([
    'scripts' =>
        '<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"' .
        ' integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>' .
        '<script src="assets/rider-map.js"></script>',
]);
