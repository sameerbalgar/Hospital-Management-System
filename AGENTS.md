# AGENTS.md

## What This Is

PHP/MySQL Hospital Management System running on XAMPP. No framework, no build system, no tests, no linter. Raw PHP with `mysqli` and Bootstrap 4.3.1.

## Setup

1. XAMPP with Apache + MySQL (MariaDB)
2. Create database `myhmsdb` in phpMyAdmin
3. Import `myhmsdb.sql` (core tables)
4. Import `pharmacy_database.sql` (pharmacy module tables)
5. Visit `localhost/Hospital-Management-System`

## Database Gotcha

`include/config.php` connects to database **`hms`**, but every `func*.php` and panel file connects to **`myhmsdb`**. The `include/` directory is a leftover from an older version of the app. **Ignore `include/config.php`** — the active DB name is `myhmsdb`.

## User Roles & Login Flow

| Role | Login Form Tab | Handler | Dashboard | Session Key |
|------|---------------|---------|-----------|-------------|
| Patient | Patient (register/login) | `func.php` / `func2.php` | `admin-panel.php` | `pid` |
| Doctor | Doctor | `func1.php` | `doctor-panel.php` | `dname` |
| Admin/Receptionist | Receptionist | `func3.php` | `admin-panel1.php` | `username` |
| Pharmacist | Pharmacist | `func_pharmacy.php` | `pharmacist-panel.php` | `pharmacist_username` |

**Patient dashboard is `admin-panel.php`** (confusing name — it's the patient's dashboard, not the admin's).

## Default Credentials (from SQL seed data)

- Admin: `admin` / `admin123`
- Doctors: `ashok`/`ashok123`, `Ganesh`/`ganesh123`, `Dinesh`/`dinesh123`, etc.
- Patients: `ram`/`ram123`, `alia`/`alia123`, `kenny`/`kenny123`, etc.
- Pharmacist: auto-seeded as `pharmacist` / `pharma123` (uses bcrypt)

## Key Files

- `func.php` — Patient login, doctor listing helpers (`display_docs`, `display_specs`), add doctor
- `func1.php` — Doctor login handler
- `func2.php` — Patient registration handler
- `func3.php` — Admin login handler (legacy, uses different DB schema assumptions — see below)
- `func_pharmacy.php` — Pharmacist login, CRUD for medicines, dispensing with transactions, supplier management. Auto-creates missing tables (`pharmacisttb`, `suppliertb`, `medicine_stock_movements`) and seeds default pharmacist + 4 suppliers on load. `medicinetb`, `pharmacy_sales`, `pharmacy_sale_items` are NOT auto-created — they come from `pharmacy_database.sql`.
- `newfunc.php` — Used by `admin-panel1.php` (includes admin pharmacists/restock handlers)
- `admin-panel.php` — Patient dashboard (book appointments, view history, prescriptions). Patient-facing "Pay Bill" removed; prescriptions link to `prescription_report.php`. Links to patient `pharmacy_bills.php`. The old `generate_bill()`/TCPDF PDF handler remains as dormant code (no button triggers it).
- `prescription_report.php` — Patient-only prescription report. Fetches `prestb` with **prepared statement** `WHERE ID = ? AND pid = ?` (session `pid`); denies cross-patient access.
- `pharmacy_bills.php` — Patient-only pharmacy bills (list + invoice detail). Uses `pharmacy_sales`/`pharmacy_sale_items`/`medicinetb`; every read scoped by session `pid` via prepared statements, so URL bill-id tampering cannot expose another patient's bill.
- `admin-panel1.php` — Admin/Receptionist dashboard (manage doctors, patients, pharmacy stock, pharmacists, suppliers)
- `doctor-panel.php` — Doctor dashboard (view appointments, prescribe)
- `pharmacist-panel.php` — Pharmacist dashboard (medicine inventory, dispensing, alerts)
- `prescribe.php` — Doctor prescription form

## `func3.php` Schema Mismatch

`func3.php` calls `display_docs()` which reads `$row['name']` from `doctb`, but the current `doctb` schema has `username` not `name`. The admin add-doctor form in `admin-panel1.php` correctly inserts `username`, `password`, `email`, `spec`, `docFees`. **If editing admin login or doctor listing, work in `admin-panel1.php` and `newfunc.php`, not `func3.php`.**

## Password Storage — Inconsistent

- `patreg`, `doctb`, `admintb`: **plaintext passwords** stored directly
- `pharmacisttb`: **bcrypt** via `password_hash()`/`password_verify()` with legacy plaintext fallback that auto-rehashes
- Any new auth code should use bcrypt. Do not change existing plaintext storage without a migration strategy.

## Logout Files

- `logout.php` — Used by patient panel, redirects to `index1.php`
- `logout1.php` — Used by doctor/admin/doctor panels, redirects to `index.php`
- Pharmacist logout: `func_pharmacy.php?logout_pharmacist=1`

## Database Tables

Core: `admintb`, `doctb`, `patreg`, `appointmenttb`, `prestb`, `contact`
Pharmacy: `medicinetb`, `pharmacisttb`, `pharmacy_sales`, `pharmacy_sale_items`, `suppliertb`, `medicine_stock_movements`

## Stale / Dead Code

- `include/` directory (`checklogin.php`, `header.php`, `sidebar.php`, `setting.php`) references non-existent files (`dashboard.php`, `book-appointment.php`, `appointment-history.php`, `edit-profile.php`, `change-password.php`, `user-login.php`). This is a different admin template not wired into the current app.
- `func3.php` `display_docs()` uses `$row['name']` which doesn't exist in `doctb` — broken.
- `index.php` links to `index1.php` for patient login — `index1.php` is the login page counterpart.

## PHP/DB Conventions

- MySQLi procedural style throughout (no PDO, no ORM)
- SQL queries built with string concatenation + `mysqli_real_escape_string` (some newer code uses prepared statements)
- Alert-based user feedback via `echo "<script>alert('...'); window.location.href = '...';</script>"`
- Timezone hardcoded to `Asia/Kolkata` in `admin-panel.php`
- TCPDF bundled in `TCPDF/` for PDF bill generation
- Bootstrap 4 loaded from CDN; jQuery loaded as slim build

## Things to Watch When Editing

- `admin-panel.php` uses `func.php` functions (`display_docs`, `display_specs`) — don't break those signatures
- `pharmacist-panel.php` uses `func_pharmacy.php` functions (`calculate_medicine_status`) — also used in `admin-panel1.php`
- Session keys differ per role — don't use `$_SESSION['pid']` in doctor code or vice versa
- `pharmacist-panel.php` uses `GET` params for search/filter, which means bookmarkable URLs but also CSRF risk
- Many files re-create `$con = mysqli_connect(...)` instead of reusing a shared connection — expect multiple connections per request
