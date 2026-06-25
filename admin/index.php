<?php
declare(strict_types=1);
require __DIR__ . '/auth.php';
$admin = require_admin_page();
$who = $admin['name'] !== '' ? $admin['name'] : $admin['username'];
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>PSV Admin — Live map</title>
  <link rel="stylesheet"
        href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
        integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY="
        crossorigin="">
  <style>
    html, body { height: 100%; margin: 0; }
    body { font-family: system-ui, sans-serif; display: flex; flex-direction: column; }
    header { background: #1565c0; color: #fff; padding: .6rem 1rem;
             display: flex; justify-content: space-between; align-items: center; flex: 0 0 auto; }
    header a { color: #fff; }
    #status { font-size: .85rem; opacity: .9; }
    #map { flex: 1 1 auto; }
    .popup-reg { font-weight: 600; }
    .seat-available { color: #1b5e20; }
    .seat-full { color: #b71c1c; }
    .seat-unknown { color: #555; }
  </style>
</head>
<body>
  <header>
    <strong>PSV Tracker — Live map</strong>
    <span id="status">Loading…</span>
    <span>Signed in as <?= htmlspecialchars($who, ENT_QUOTES) ?> · <a href="logout.php">Log out</a></span>
  </header>
  <div id="map"></div>

  <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
          integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo="
          crossorigin=""></script>
  <script src="assets/map.js"></script>
</body>
</html>
