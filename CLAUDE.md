# App Magasin — CLAUDE.md

## What this app is
Equipment loan management system ("gestion des emprunts de matériel") for a school (BTS MAV, Pointe-Noire). Manages physical assets, reservations, licenses, consumables, rooms, and maintenance.

## Stack
- **Backend**: Laravel 11 (PHP 8.2), SQLite database at `database/database.sqlite`
- **Frontend**: Vue 3 + Inertia.js (SSR-less SPA), Tailwind CSS 3, Heroicons, FullCalendar, Chart.js
- **Auth/Roles**: Laravel Breeze + Spatie `laravel-permission` (roles: `admin`)
- **PDF**: barryvdh/laravel-dompdf
- **QR/Barcode**: simplesoftwareio/simple-qrcode, jsbarcode
- **Build**: Vite

## Environment
- **Server**: Windows Server 2019, IIS, running at `http://10.15.80.229:8080`
- **Working dir**: `C:\inetpub\wwwroot\app-magasin`
- **App locale**: `fr` (French UI)
- **Mail**: Gmail SMTP via `btspointenoire.magasin@gmail.com`
- **Timezone**: America/La_Paz

## Key directories
```
app/
  Http/Controllers/   — one controller per feature
  Models/             — Eloquent models
  Mail/               — Mailable classes
  Services/           — business logic
resources/
  js/
    Pages/            — Vue page components (per feature folder)
    Components/       — shared Vue components
    Layouts/          — AppLayout etc.
database/
  migrations/         — SQLite migrations
  seeders/
routes/
  web.php             — all routes (auth-gated, role-gated with 'role:admin')
public/build/         — Vite compiled assets (do NOT edit, regenerate with `npm run build`)
```

## Development workflow
Since the app runs live on this machine, changes to PHP files take effect immediately. Frontend changes require a build:

```bash
# Build frontend assets (run from project root)
npm run build

# Run a migration
php artisan migrate

# Clear caches after config/route changes
php artisan config:clear && php artisan route:clear && php artisan view:clear
```

## Conventions
- All user-facing text is in **French**
- Routes follow REST naming; static routes declared **before** parameterized routes in `web.php`
- Admin-only routes wrapped in `Route::middleware('role:admin')`
- Inertia responses use `Inertia::render('PageFolder/Component', [...props])`
- Models use `Setting::get('key', 'default')` for app-level settings
- `spatie/laravel-permission`: check roles with `hasRole('admin')`, not `is_admin`

## Notable features
- Asset lifecycle: create → assign → checkout (via reservation or direct) → return
- Reservation workflow: create → supervisor approve → admin approve → checkout → return
- QR/barcode scanning with paired smartphone ↔ desktop sessions
- To-Do list (projects, tasks, kanban, labels, linked to reservations)
- Equipment kits, consumables with stock management
- Room booking system
- Maintenance tracking
- Backups (create/restore SQLite)
- Snipe-IT import (CSV + full ZIP backup)
- PDF print views for reservations
- Email notifications (embedded logos via base64)
