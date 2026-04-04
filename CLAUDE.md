# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Commands

```bash
# Full setup (install deps, generate key, migrate, build assets)
composer setup

# Start all dev servers (Laravel, queue, logs, Vite) concurrently
composer dev

# Run tests (clears config cache first)
composer test

# Run a single test file or method
php artisan test tests/Feature/ExampleTest.php
php artisan test --filter=test_method_name

# Lint PHP code
./vendor/bin/pint

# Build frontend assets
npm run build

# Start Vite dev server only
npm run dev
```

## Architecture

This is a Laravel 13 application with:

- **Backend:** Laravel 13, PHP, SQLite (default), Eloquent ORM
- **Frontend:** Vite 8 + TailwindCSS 4 + Alpine.js, Axios for HTTP

### Request Flow

HTTP requests enter via `public/index.php` → `bootstrap/app.php` → routes in `routes/web.php` → controllers in `app/Http/Controllers/` → Blade views in `resources/views/`.

Artisan CLI commands are defined in `routes/console.php`.

### Key Locations

- `bootstrap/app.php` — middleware and exception handler registration
- `bootstrap/providers.php` — service provider list
- `app/Providers/AppServiceProvider.php` — service bindings and boot logic
- `resources/js/bootstrap.js` — Axios setup with CSRF token header
- `vite.config.js` — asset pipeline (JS entry: `resources/js/app.js`, CSS: `resources/css/app.css`)

### Database

Uses SQLite by default (`database/database.sqlite`). Three pre-existing migrations: `users`, `cache`, and `jobs` tables. Session storage is database-backed.
