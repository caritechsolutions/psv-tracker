<?php
declare(strict_types=1);
require __DIR__ . '/db.php';

// GET /api/ads.php — PUBLIC, unauthenticated, read-only.
// Returns the currently-active banner ads for the global rotation. This is what
// the rider app/web will consume. No admin fields are exposed.
//
// "Currently active" = active = 1 AND today is within [starts_on, ends_on]
// (either bound NULL means open-ended). Ordered by weight then id, the same
// order the admin list shows, so admin and rider see the same rotation.

// Public URL base for stored images. Must match the nginx alias for /uploads/.
const ADS_PUBLIC_URL = '/uploads/';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    json_response(405, ['ok' => false, 'error' => 'method_not_allowed']);
}

// Read-only public feed — allow cross-origin reads (e.g. a web rider client).
header('Access-Control-Allow-Origin: *');

$stmt = db()->query(
    'SELECT id, title, image_file, click_url, weight
       FROM ads
      WHERE active = 1
        AND (starts_on IS NULL OR starts_on <= CURDATE())
        AND (ends_on   IS NULL OR ends_on   >= CURDATE())
      ORDER BY weight ASC, id ASC'
);

$ads = array_map(static function (array $row): array {
    return [
        'id'        => (int) $row['id'],
        'image_url' => ADS_PUBLIC_URL . rawurlencode($row['image_file']),
        'click_url' => $row['click_url'],          // may be null
        'alt'       => $row['title'],
        'weight'    => (int) $row['weight'],
    ];
}, $stmt->fetchAll());

json_response(200, ['ok' => true, 'ads' => $ads]);
