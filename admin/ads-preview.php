<?php
declare(strict_types=1);
require __DIR__ . '/auth.php';
require_admin_page();
// Guarded preview of the public rotation. Renders whatever the UNauthenticated
// api/ads.php currently serves, so we can watch the banner loop before the
// rider app exists. Deliberately bare (not the admin shell) to approximate the
// rider view.
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Ad rotation preview</title>
  <style>
    body { margin: 0; font-family: system-ui, sans-serif; background: #222; color: #eee;
           min-height: 100vh; display: flex; flex-direction: column; align-items: center; }
    .bar { width: 100%; background: #111; padding: .5rem 1rem; box-sizing: border-box;
           display: flex; justify-content: space-between; align-items: center; }
    .bar a { color: #8ab4f8; }
    .stage { flex: 1; display: flex; align-items: center; justify-content: center; padding: 1rem; }
    .banner { max-width: 100%; }
    .banner img { max-width: 100%; max-height: 70vh; display: block; box-shadow: 0 2px 14px rgba(0,0,0,.6); }
    .caption { text-align: center; margin-top: .6rem; font-size: .9rem; color: #aaa; }
    .empty { color: #aaa; }
  </style>
</head>
<body>
  <div class="bar">
    <strong>Ad rotation preview</strong>
    <span><span id="idx"></span> &middot; <a href="ads.php">Back to Ads</a></span>
  </div>
  <div class="stage">
    <div id="banner" class="banner"><span class="empty">Loading&hellip;</span></div>
  </div>

  <script>
    'use strict';
    var ROTATE_MS = 3000;
    var banner = document.getElementById('banner');
    var idxEl = document.getElementById('idx');
    var ads = [];
    var i = 0;
    var timer = null;

    function esc(s) {
      return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
        return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
      });
    }

    function show() {
      if (ads.length === 0) {
        banner.innerHTML = '<span class="empty">No active ads right now.</span>';
        idxEl.textContent = '0 ads';
        return;
      }
      var ad = ads[i % ads.length];
      var img = '<img src="' + esc(ad.image_url) + '" alt="' + esc(ad.alt) + '">';
      var inner = ad.click_url
        ? '<a href="' + esc(ad.click_url) + '" target="_blank" rel="noopener">' + img + '</a>'
        : img;
      banner.innerHTML = inner + '<div class="caption">' + esc(ad.alt) + '</div>';
      idxEl.textContent = (i % ads.length + 1) + ' / ' + ads.length;
    }

    function load() {
      fetch('../api/ads.php', { headers: { Accept: 'application/json' } })
        .then(function (r) { return r.json(); })
        .then(function (d) {
          ads = (d && d.ads) || [];
          i = 0;
          show();
        })
        .catch(function () { banner.innerHTML = '<span class="empty">Could not load ads.</span>'; });
    }

    function tick() { i++; show(); }

    load();
    setInterval(load, 15000);     // pick up admin changes
    timer = setInterval(tick, ROTATE_MS);
  </script>
</body>
</html>
