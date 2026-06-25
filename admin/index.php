<?php
declare(strict_types=1);
require __DIR__ . '/auth.php';
require __DIR__ . '/partials/layout.php';
$admin = require_admin_page();

admin_layout_start([
    'admin'      => $admin,
    'active'     => 'map',
    'title'      => 'Live map',
    'full_bleed' => true,
    'show_count' => true,
    'head'       =>
        '<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"' .
        ' integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">',
]);
?>
<div class="map-wrap">
  <div id="map"></div>

  <aside id="panel" class="panel">
    <div class="panel-head">
      <span class="title">Vehicles</span>
      <span id="panel-count" class="count"></span>
      <button id="panel-toggle" class="panel-toggle" type="button"
              aria-label="Collapse panel" title="Collapse panel">
        <i class="ti ti-chevron-left" aria-hidden="true"></i>
      </button>
    </div>
    <div class="panel-body">
      <div class="panel-filters">
        <input id="flt-search" type="search" placeholder="Search registration or route&hellip;"
               autocomplete="off" aria-label="Search vehicles">
        <div class="filter-row">
          <select id="flt-route" aria-label="Filter by route">
            <option value="">All routes</option>
          </select>
          <select id="flt-seat" aria-label="Filter by seat status">
            <option value="">All seats</option>
            <option value="available">Available</option>
            <option value="full">Full</option>
            <option value="unknown">Unknown</option>
          </select>
        </div>
      </div>
      <ul id="vehicle-list" class="vehicle-list"></ul>
    </div>
  </aside>
</div>
<?php
admin_layout_end([
    'scripts' =>
        '<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"' .
        ' integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>' .
        '<script src="assets/map.js"></script>',
]);
