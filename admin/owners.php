<?php
declare(strict_types=1);
require __DIR__ . '/auth.php';
require __DIR__ . '/partials/layout.php';
$admin = require_admin_page();

admin_layout_start(['admin' => $admin, 'active' => 'owners', 'title' => 'Owners']);
admin_coming_soon('Owners', 'ti-users');
admin_layout_end();
