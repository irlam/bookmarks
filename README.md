# 🔖 Bookmarks

A private, self-hosted bookmark manager for [bookmarks.chrisirlam.com](https://bookmarks.chrisirlam.com/).

## Features

- 🔐 Password-protected login (session-based, bcrypt hash)
- 📌 Save, view, search, tag, edit and delete bookmarks
- 🏷️ Tag filtering sidebar
- 🌑 Modern dark theme
- 🔮 "Fetch title" auto-populates the title from a URL
- 📁 No database — bookmarks stored in a plain JSON file
- ✅ PHP 8.x compatible, works on standard Plesk shared hosting
- 🚫 No Docker, no Node build step

## Stack

- PHP 8.x
- Bootstrap 5.3 (vendored — no CDN required)
- Bootstrap Icons (vendored)
- Vanilla JavaScript (Fetch API)
- JSON flat-file storage

## File structure

```
├── config.php           # App config — set your password hash here
├── auth.php             # Session auth helper
├── login.php            # Login page
├── logout.php           # Logout handler
├── index.php            # Main app page
├── api.php              # JSON REST API (list / add / update / delete)
├── assets/
│   ├── style.css        # Dark-theme CSS
│   ├── app.js           # Frontend JavaScript
│   └── vendor/          # Bootstrap + Bootstrap Icons (vendored)
├── data/
│   ├── .htaccess        # Blocks direct HTTP access to data dir
│   └── bookmarks.json   # Bookmark data (auto-created on first save)
└── .htaccess            # Protects config.php and auth.php
```

## Setup

1. Upload all files to your hosting root (e.g. `public_html/`).

2. Generate a password hash:
   ```bash
   php -r "echo password_hash('your_password', PASSWORD_DEFAULT);"
   ```

3. Edit `config.php` and replace `APP_PASSWORD_HASH` with your hash.

4. Ensure the `data/` directory is writable by PHP:
   ```bash
   chmod 750 data/
   ```

5. Visit your site and log in.

## Default credentials

The default password is **`bookmarks`** — **change this before deploying!**

## Security notes

- Passwords are hashed with `password_hash()` / `password_verify()` (bcrypt).
- All mutating API actions are CSRF-protected.
- The `data/` directory and `config.php`/`auth.php` are blocked from direct HTTP access via `.htaccess`.
- Sessions use `HttpOnly`, `SameSite=Strict` cookies.
