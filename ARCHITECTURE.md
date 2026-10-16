# Architecture Documentation

The system follows a decoupled architecture where the **Frontend is the UI** and the **Backend is the Function/API**:

```
Browser
  │
  ▼
[ Frontend (React 19 + TypeScript + Vite) ]  (Port 5173)
  │
  │ HTTP (REST JSON / Sanctum Bearer Token)
  ▼
[ Backend (Laravel 12 API Service) ]          (Port 8000)
  │
  ▼
[ MySQL Database `bhms` ]
```

---

## 1. Frontend Architecture (`frontend/`)

- **Tech Stack**: React 19, TypeScript 5.7, Vite 6.
- **Routing & Navigation**: Dynamic role-based navigation with separate page modules.
- **State Management & Contexts**:
  - `AuthContext`: Tracks user session, Bearer tokens, roles, login, registration, and logout.
  - `ThemeContext`: Handles light/dark theme switching and persistence.
- **Dedicated Pages**:
  - `AuthPage`: Sign-in and tenant self-registration.
  - `DashboardPage`: Overview cards, revenue metrics, and quick links tailored to role.
  - `RoomsPage`: Room listing, status filters, Add/Edit room modals, availability toggle, and tenant inquiry submission.
  - `LeasesPage`: Active and past lease tracking, room-to-tenant assignment form, and contract termination.
  - `InquiriesPage`: Landlord approval/rejection pipeline for tenant room applications.
  - `BillingPage`: Incidental charges ledger, invoice generation by period, payment recording modal.
  - `AccountsPage`: Admin user management (roles, activation toggle, credentials).
  - `ActivityPage`: Administrative audit logs and searchable activity trail.
- **API Client (`src/api/client.ts`)**:
  - Handles JSON payloads, Bearer authentication headers, token expiry, and pagination resolution (`fetchAll`).
- **Types (`src/types/`)**:
  - Fully typed TypeScript definitions for `User`, `Room`, `Lease`, `Inquiry`, `Invoice`, `Charge`, and `ActivityLog`.

---

## 2. Backend Architecture (`backend/`)

- **Tech Stack**: PHP 8.2+, Laravel 12.
- **Pure Function & Headless Service**:
  - All web views and frontend assets have been removed from the backend.
  - Root route `GET /` returns a JSON API health and service status payload.
  - Routes in `routes/api.php` provide REST endpoints protected with Sanctum middleware and role authorization.
- **Key Modules & Controllers**:
  - `AuthController`: Handles registration, login (rate-limited), session retrieval, and token revocation.
  - `RoomController`: Room CRUD, image management, and availability toggling.
  - `InquiryController`: Tenant room requests, landlord approval, and conflict-safe lease creation.
  - `LeaseController`: Lease lifecycle and pending charge management.
  - `InvoiceController`: Monthly invoice generation and payment recording.
  - `DashboardController`: Metric aggregation (occupancy, revenue, pending counts).
  - `Admin\UserController` & `Admin\ActivityLogController`: System administration and auditing.
- **CORS Configuration**: Configured in `config/cors.php` to accept frontend origins on standard development ports.

---

## 3. Legacy Migration & Full Transition

- Legacy procedural PHP scripts have been completely decommissioned and all functional requirements ported into the Laravel backend and React frontend.
- Automated room status and lease expiration sync runs via the `leases:sync-expired` artisan command on a daily schedule.
- Room availability validation is handled natively via `GET /rooms/{room}/availability-status`.
- Historical database records can be imported using `php artisan legacy:import`.
