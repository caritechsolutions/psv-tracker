<?php
declare(strict_types=1);
require __DIR__ . '/auth.php';
require __DIR__ . '/partials/layout.php';
$admin = require_admin_page();

admin_layout_start(['admin' => $admin, 'active' => 'ads', 'title' => 'Ads']);
admin_coming_soon('Ads', 'ti-speakerphone');
admin_layout_end();
