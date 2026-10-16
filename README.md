# Boarding House Management System (BHMS)

A decoupled full-stack property management application built with a **React + TypeScript frontend** and a **Laravel REST API backend**.

```
Boarding-House-Management-System/
├── frontend/    # Standalone React + TypeScript SPA (UI layer)
│   ├── src/
│   │   ├── api/          # Typed API client with token management & helpers
│   │   ├── components/   # Layout (Sidebar, Topbar), Modals, Cards, Badges
│   │   ├── context/      # AuthContext (roles, tokens) & ThemeContext (light/dark)
│   │   ├── pages/        # Dedicated pages (Auth, Dashboard, Rooms, Leases, Inquiries, Billing, Accounts, Activity)
│   │   ├── styles/       # Responsive theme stylesheets (dark/light mode)
│   │   └── types/        # TypeScript interfaces (User, Room, Lease, Inquiry, Invoice, Charge, etc.)
│   ├── package.json
│   └── vite.config.ts    # Configured with proxy to Laravel backend
│
├── backend/     # Headless Laravel REST API (Function & Data layer)
│   ├── app/Http/Controllers/Api/  # REST API controllers
│   ├── app/Models/                # Eloquent models & relationships
│   ├── app/Services/              # Domain services (billing, approvals, logging)
│   ├── database/migrations/       # Database schema migrations
│   ├── routes/api.php             # Protected & public API routes
│   └── routes/web.php             # API health check endpoint
└── index.php        # Redirect to React frontend
```

---

## Getting Started

### 1. Database Setup (MySQL / XAMPP)
1. Start Apache and MySQL in your XAMPP Control Panel.
2. In phpMyAdmin, create a database named `bhms` (or import `backend/database/create_bhms_database.sql`).

### 2. Backend Setup (Laravel API)
1. Open a terminal in `backend/`:
   ```bash
   cd backend
   ```
2. Copy environment file if needed and verify DB connection:
   ```bash
   cp .env.example .env
   ```
3. Install PHP dependencies and generate app key:
   ```bash
   composer install
   php artisan key:generate
   ```
4. Run migrations and database seeder:
   ```bash
   php artisan migrate --seed
   ```
5. Start the backend API server:
   ```bash
   php artisan serve --host=127.0.0.1 --port=8000
   ```
   *The API will be live at `http://127.0.0.1:8000`.*

### 3. Frontend Setup (React + TypeScript)
1. Open a second terminal in `frontend/`:
   ```bash
   cd frontend
   ```
2. Install node dependencies:
   ```bash
   npm install
   ```
3. Start the Vite development server:
   ```bash
   npm run dev
   ```
4. Open **`http://localhost:5173`** in your browser!

---

## Development Accounts

The database seeder creates default test accounts:

| Role | Email | Password |
| :--- | :--- | :--- |
| **Admin** | `admin@bhms.test` | `password` |
| **Landlord** | `landlord@bhms.test` | `password` |
| **Tenant 1** | `tenant@bhms.test` | `password` |
| **Tenant 2** | `tenant2@bhms.test` | `password` |

*New tenants can also self-register directly on the sign-in page.*

---

## Key Features & Pages

- **Authentication & Roles**: Secure Sanctum token authentication with role-based routing (Admin, Landlord, Staff, Tenant).
- **Dashboard (`DashboardPage`)**: Real-time property occupancy metrics, monthly revenue, active lease counts, and quick shortcuts.
- **Room Inventory (`RoomsPage`)**: Filter by availability, Add/Edit room modals, room maintenance toggling, and tenant inquiry submission.
- **Tenants & Occupancy (`LeasesPage`)**: Create new leases with room and tenant pickers, customize rent, and end leases.
- **Room Requests (`InquiriesPage`)**: Tenant rental applications with one-click approval (auto-generating leases) or rejection.
- **Billing & Invoices (`BillingPage`)**: Record incidental charges (canteen, appliance, utilities), generate monthly invoices, and record payments with balances.
- **User Accounts (`AccountsPage`)**: Admin user management: assign roles, activate/deactivate accounts, change emails, and reset passwords.
- **Activity Log (`ActivityPage`)**: Searchable audit trail recording operational events and transactions.
- **Theme Support**: High-contrast, responsive light mode and dark mode with persistent preferences.

---

## Testing

Run the automated backend test suite:
```bash
cd backend
php artisan test
```
Build the TypeScript frontend for production:
```bash
cd frontend
npm run build
```
