<?php
declare(strict_types=1);
require __DIR__ . '/auth.php';
require __DIR__ . '/partials/layout.php';
$admin = require_admin_page();

admin_layout_start(['admin' => $admin, 'active' => 'settings', 'title' => 'Settings']);
admin_coming_soon('Settings', 'ti-settings');
admin_layout_end();
