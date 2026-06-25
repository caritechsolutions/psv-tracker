<?php
// Reference only. install.sh generates the real api/config.php on the server
// (with a randomly generated DB password) and that real file is git-ignored.
return [
    'db_host'    => '127.0.0.1',
    'db_name'    => 'psv_tracker',
    'db_user'    => 'psv_user',
    'db_pass'    => 'CHANGE_ME',
    'db_charset' => 'utf8mb4',
];
