# System architecture

The active application is a React frontend served by the Laravel application in `backend/`. Laravel is the system of record for API authentication, application logic, and database writes.

```text
Browser -> React (Laravel Vite build) -> same-origin /api requests -> Laravel controllers/services -> MySQL database `bhms`
```

## React frontend

- `backend/resources/views/app.blade.php` is the Laravel page shell and React mount point.
- `backend/resources/js/app.jsx` contains the React interface: sign-in/tenant registration, dashboard, room inventory, tenant requests, leases/occupancy, billing, user accounts, and admin activity log.
- `backend/resources/js/api.js` is the shared API client. It sends JSON requests to Laravel `/api`, attaches the signed-in Sanctum token, handles validation errors, and clears invalid sessions.
- `backend/resources/css/app.css` provides the accessible responsive theme (soft off-white/navy in light mode and charcoal/light text in dark mode).
- `backend/vite.config.js` builds the React entry into Laravel's `public/build` assets.

## Laravel backend

- `backend/routes/web.php` serves the React app at `/`.
- `backend/routes/api.php` defines authentication and protected REST endpoints.
- `backend/app/Http/Controllers/Api/` and `Api/Admin/` implement those endpoints.
- `backend/app/Http/Requests/` validates incoming data; `app/Http/Resources/` shapes JSON responses.
- `backend/app/Models/`, `app/Policies/`, and `app/Services/` hold the data model, authorization, invoice generation, and inquiry/lease workflows.
- `backend/app/Console/Commands/ImportLegacyData.php` provides the optional `php artisan legacy:import` migration for old users, guests, rooms, and reservations. The importer records source-to-target IDs in `legacy_imports`, so it can be safely rerun.
- `backend/database/migrations/` is the Laravel schema. Use the Laravel database only; do not point it at the legacy PHP schema.
- `backend/.env` configures the local Laravel database. The checked-in template is `backend/.env.example`.

## Main UI-to-API flows

- Sign-in and tenant self-registration: `POST /api/login`, `POST /api/register`, `GET /api/me`, `POST /api/logout`.
- Rooms: `GET/POST /api/rooms`, `PUT/DELETE /api/rooms/{room}`, and `PATCH /api/rooms/{room}/availability`.
- Tenant requests: `GET /api/inquiries`, `POST /api/rooms/{room}/inquiries`, and landlord/admin approval or rejection endpoints.
- Tenants and leases: `GET /api/tenants`, `GET/POST /api/leases`, and `POST /api/leases/{lease}/end`.
- Charges, invoices, and payments: lease charge endpoints, `POST /api/invoices/generate`, invoice listing, and payment recording.
- Admin tools: account management and activity log endpoints under `/api/admin`.

The login screen accepts email or username. Tenant accounts use Laravel credentials. Legacy staff accounts map to the Laravel `staff` role; imported rooms are assigned to the first imported staff account when one exists. Imported guest accounts start disabled because the former application did not give guests passwords; an administrator can set a password, update the email, and enable the account.

## Legacy PHP application

The PHP files in the project root (`home.php`, `for_rooms.php`, `for_guests.php`, `for_reservations.php`, `for_charges.php`, `for_invoices.php`, `connect_db.php`, and related files) are the former standalone system. They use MySQLi, username/session authentication, and the separate `boarding_house_management_system` schema. They are retained as a legacy reference and are no longer the active frontend or API. Do not mix their tables with Laravel's database; the models and account/authentication rules differ.

## Local setup

1. Start MySQL in XAMPP and create the dedicated Laravel database by importing `backend/database/create_bhms_database.sql` in phpMyAdmin.
2. In `backend/`, copy `.env.example` to `.env` if needed, configure `DB_*` to the new `bhms` database, and run `php artisan key:generate`.
3. Run `composer install`, `php artisan migrate --seed`, and `npm.cmd install` from `backend/`.
4. Run `npm.cmd run build` for a production-style local build, or `npm.cmd run dev` while developing.
5. Run `php artisan serve --host=127.0.0.1 --port=8000` from `backend/`, then open `http://127.0.0.1:8000`.

The development seeder creates `admin@bhms.test`, `landlord@bhms.test`, `tenant@bhms.test`, and `tenant2@bhms.test`, each with the initial password `password`. Change or replace these development credentials before using real data. Tenants can also register through the React sign-in page. Existing records are not merged automatically; after setting `LEGACY_DB_*` in `backend/.env`, an administrator may run `php artisan legacy:import`. It reads the former `boarding_house_management_system` database and writes mapped accounts, rooms, and leases to `bhms`; it does not modify the source database.
