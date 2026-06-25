<?php
declare(strict_types=1);
require __DIR__ . '/auth.php';
require __DIR__ . '/partials/layout.php';

$owner = require_owner_page();

owner_layout_start([
    'owner' => $owner,
    'title' => 'Live map',
    'head'  => OWNER_LEAFLET_CSS,
]);
?>
<div class="map-wrap">
  <div id="map"></div>
  <aside class="owner-panel">
    <div class="owner-panel-head">
      <span class="title">My vehicles</span>
      <span id="status" class="count">Loading&hellip;</span>
    </div>
    <ul id="vehicle-list" class="vehicle-list"></ul>
  </aside>
</div>
<?php
owner_layout_end([
    'scripts' =>
        '<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"' .
        ' integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>' .
        '<script src="assets/owner-map.js"></script>',
]);
