<?php
// ─── Bookmarks App Configuration ────────────────────────────────────────────

// Default password is 'bookmarks'
// To generate a new hash run: php -r "echo password_hash('your_password', PASSWORD_DEFAULT);"
define('APP_PASSWORD_HASH', '$2y$10$RWEfOdzUZLc4h63bhBb2xu4.ON26fRj077nJJ/9Fb9/wCegC6o3Cq');

// Absolute path to the JSON data file
define('DATA_FILE', __DIR__ . '/data/bookmarks.json');

// Session cookie name
define('SESSION_NAME', 'bm_sess');
