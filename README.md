# Boarding House Management System

The active application is a React frontend served by the Laravel backend in `backend/`. The PHP files in the project root are the previous version; opening the project root redirects to Laravel at `http://127.0.0.1:8000/` by default.

## Run locally with XAMPP MySQL

1. Start MySQL in XAMPP.
2. In phpMyAdmin, import [`backend/database/create_bhms_database.sql`](backend/database/create_bhms_database.sql). This makes a separate `bhms` database; it does not overwrite the legacy `boarding_house_management_system` database.
3. In a terminal, change to `backend/`. Copy `.env.example` to `.env` if `.env` does not exist. Set `DB_HOST=127.0.0.1`, `DB_PORT=3306`, `DB_DATABASE=bhms`, `DB_USERNAME=root`, and the MySQL password used by your local XAMPP setup.
4. Run `composer install`, `php artisan key:generate`, `php artisan migrate --seed`, and `npm.cmd install`.
5. Run `npm.cmd run build` to build the React assets.
6. Start Laravel with `php artisan serve --host=127.0.0.1 --port=8000` and open `http://127.0.0.1:8000/`.

To copy the former PHP users, guests, rooms, and reservations, configure the `LEGACY_DB_*` values in `.env` to point at the old `boarding_house_management_system` database, then run `php artisan legacy:import`. The import is repeat-safe and leaves the source database untouched. Imported guest accounts are disabled until an admin assigns a password and enables them in User accounts. This step is optional for a new installation.

For frontend development with Vite hot reload, use `npm.cmd run dev` instead of building, while keeping the Laravel server running. `BHMS_APP_URL` can override the redirect used by the legacy project root `index.php`.

## Initial development accounts

The database seeder creates:

- Admin: `admin@bhms.test` / `password`
- Landlord: `landlord@bhms.test` / `password`
- Tenant: `tenant@bhms.test` / `password`
- Tenant: `tenant2@bhms.test` / `password`

These are local development credentials. Change them before deploying or using real tenant data. New tenants can register from the React sign-in screen. Admins can create staff, landlord, tenant, and admin accounts.

## Features

- Role-based sign-in, tenant registration, and account access management.
- Room inventory and availability, tenant room requests, and request approval.
- Lease creation/end dates and occupancy tracking.
- Pending canteen/appliance charges, monthly invoice generation, and payment recording.
- Admin activity log and role-aware dashboard.
- Responsive, high-contrast light/dark interface with larger form controls.

The React app calls Laravel's same-origin `/api` endpoints. Use the Laravel migrations as the source of truth. The old PHP schema remains separate; legacy records are only copied when the importer is run explicitly.

## Development checks

Run `npm.cmd run build` in `backend/` after frontend changes. Laravel feature tests can be run with `php artisan test` in `backend/`.
