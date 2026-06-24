# MMU Student Enrollment System (SES) — v2

**TSE6223 Software Engineering Fundamentals — Trimester 2, 2026 / 2027**
**Faculty of Information Science & Technology, Multimedia University**
*Team: The Tech Titan*

A web-based Student Enrollment System built with **PHP 8**, **MySQL**, **HTML**, **CSS**, and light vanilla **JavaScript**. The system implements every functional and non-functional requirement in the project report, plus the additional academic constraints expected of a real-world enrollment platform.

---

## ✨ What's new in v2

This release responds to faculty-grade feedback by lifting the system from a basic CRUD app to an enterprise-style application:

| Area | v1 | v2 |
|---|---|---|
| Architecture | Page-centric procedural code with mixed SQL + HTML | **Lightweight MVC** — `models/` for SQL, `controllers/` for business logic, page files are thin controllers, layouts in `includes/` |
| Course prerequisites | None | **`course_prerequisites` table + enforcement on enrol** |
| Credit-hour cap | None | **Max 22 credit-hours per trimester** (`config/config.php`) |
| Full-course handling | Hard "course is full" error | **Automated waitlist** with positions + auto-promote on drop |
| Registration slip | None | **Printable confirmation slip** (Save as PDF in any browser) |
| Forms security | Open POST | **CSRF tokens on every POST**, verified per request |
| Session security | Session ID rotation only | + `httponly` + `samesite=Lax` cookie params, no `?>` trailing whitespace |
| UI feedback | Generic alerts | **Coloured flash banners + status badges** (Enrolled / Waitlisted / Dropped / Completed) |

---

## 🏛 Architecture

```
mmu_ses/
├── config/
│   └── config.php              ← business-rule constants (credit cap, branding, timezone)
├── includes/
│   ├── bootstrap.php           ← single entry point: config + autoloader + session + helpers
│   ├── db_credentials.php      ← DB host/user/password (edit for production)
│   ├── auth.php                ← session helpers, flash, status_badge(), require_*()
│   ├── header.php / footer.php ← shared layout (sidebar + topbar)
│   └── db.php                  ← legacy shim, delegates to Database::pdo()
├── models/                     ← Data access — every SQL statement lives here
│   ├── Database.php            ← PDO singleton (prepared statements, ERRMODE_EXCEPTION)
│   ├── StudentModel.php
│   ├── AdminModel.php
│   ├── CourseModel.php
│   ├── EnrollmentModel.php     ← atomic enrol/drop with row locks + waitlist
│   └── PrerequisiteModel.php
├── controllers/                ← Business logic — coordinates models, returns shaped results
│   ├── AuthController.php      ← login/logout + CSRF helpers
│   └── EnrollmentController.php← prereq check + credit-cap check + enrol/drop wrappers
├── database/
│   ├── ses_setup.sql           ← fresh-install schema + seed data
│   └── ses_migration_v1_to_v2.sql
├── assets/css/style.css        ← single stylesheet (responsive, print-friendly)
├── index.php  login.php  register.php  logout.php
├── student/                    ← Student-facing pages (thin controllers)
│   ├── dashboard.php
│   ├── courses.php             ← FR-02 (with prereq + credit + waitlist UX)
│   ├── my_courses.php          ← FR-03 (add / drop / withdraw waitlist)
│   ├── waitlist.php            ← My queue positions
│   ├── timetable.php           ← weekly grid auto-built from schedule_info
│   ├── slip.php                ← printable registration confirmation
│   └── profile.php
└── admin/
    ├── dashboard.php
    ├── students.php            ← FR-04 (CRUD + password reset)
    ├── courses.php             ← CRUD with quota safety check
    ├── prerequisites.php       ← Add / remove prereq links
    ├── enrollments.php         ← System-wide view, filterable
    └── reports.php             ← Stats + CSV exports
```

### How a request flows (example: a student clicks **Enrol**)

1. Browser POSTs to `student/courses.php`.
2. Page (controller) calls `AuthController::verifyCsrf()` — aborts with HTTP 419 if the token is missing or wrong.
3. Page calls `EnrollmentController::enroll($student_id, $course_id)`.
4. Controller checks prerequisites via `PrerequisiteModel` + `EnrollmentModel::completedCourseIds()`.
5. Controller checks credit-hour cap via `EnrollmentModel::currentCreditHours()` + `CourseModel::findById()`.
6. Controller calls `EnrollmentModel::enroll()`, which opens a **transaction**, **locks the courses row**, decides enrolled vs waitlisted, updates the right tables, and commits.
7. Controller returns `['ok' => bool, 'kind' => 'enrolled'|'waitlisted'|'prereq'|'credit_cap'|'error', 'message' => string]`.
8. Page renders the result as a flash banner and redirects (POST-redirect-GET).

No SQL is written outside `models/`.

---

## ✅ Requirements Coverage

### Functional Requirements

| ID    | Requirement                              | Where it lives                                                                |
|-------|------------------------------------------|-------------------------------------------------------------------------------|
| FR-01 | User authentication & registration       | `login.php`, `register.php`, `AuthController`, `StudentModel::authenticate()` |
| FR-02 | Browse & enrol in courses                | `student/courses.php`, `EnrollmentController::enroll()`                       |
| FR-03 | Add / drop subjects                      | `student/my_courses.php`, `EnrollmentController::drop()`                      |
| FR-04 | Admin: manage students & courses         | `admin/students.php`, `admin/courses.php`, `admin/prerequisites.php`          |

### Non-Functional Requirements

| ID     | Requirement                            | Implementation                                                              |
|--------|----------------------------------------|-----------------------------------------------------------------------------|
| NFR-01 | Password encryption (SHA-256)          | **Bcrypt** via `password_hash(PASSWORD_BCRYPT)` — stronger than SHA-256     |
| NFR-02 | Availability                           | Stateless PHP — runs on standard LAMP/XAMPP, no special services            |
| NFR-03 | Enrol in < 2s                          | Indexed queries + single DB transaction per enrolment, no N+1               |

### Extended business rules (v2)

| Rule | Implementation |
|---|---|
| **Course prerequisites** | `course_prerequisites` table; checked by `EnrollmentController::checkPrerequisitesMet()` before any enrol |
| **Credit-hour cap (22)** | `MAX_CREDIT_HOURS_PER_TRIMESTER` in `config/config.php`, enforced by `EnrollmentController::checkCreditCap()` |
| **Automated waitlisting** | `enrollments.status = 'waitlisted'` + `waitlist_position`; on drop, head of queue is auto-promoted (`EnrollmentModel::drop()`) |
| **Registration confirmation slip** | `student/slip.php` — printable HTML with `@media print` CSS so any browser can Save as PDF |
| **CSRF protection** | `AuthController::csrfField()` on every form, `AuthController::verifyCsrf()` at the top of every POST branch |
| **Privilege validation** | `require_student()` / `require_admin()` at the top of every protected page; URL-typing into `admin/dashboard.php` redirects to login |

---

## 🛠 Setup Instructions (XAMPP)

### 1. Install XAMPP
Download from <https://www.apachefriends.org/> and install. Start **Apache** and **MySQL** from the Control Panel.

### 2. Copy the project into htdocs

```
C:\xampp\htdocs\mmu_ses\
```

(macOS: `/Applications/XAMPP/htdocs/mmu_ses/`. Linux: `/opt/lampp/htdocs/mmu_ses/`.)

### 3. Create the database
1. Open `http://localhost/phpmyadmin`.
2. Click **Import**, choose `database/ses_setup.sql`.
3. Click **Go**.

This creates the `mmu_ses` database with: 4 tables + course_prerequisites table, seed data of 2 admins, 4 students (the team), 10 courses, 5 prerequisite links, and a few "completed" enrolments so the prereq demo works.

**Upgrading from v1?** Run `database/ses_migration_v1_to_v2.sql` instead — it adds the new columns/table without wiping your data.

### 4. Configure DB credentials (only if not default XAMPP)
Edit `includes/db_credentials.php` if your MySQL `root` user has a password, or if the database lives on a different host.

### 5. Open the site
<http://localhost/mmu_ses/>

---

## 🔐 Demo Accounts

All passwords are: **`password123`**

**Students** (the team):
- `253UT256KY` — Andrew Lim Zi Fei
- `253UT256JW` — Desmond Choi Lip Sheng
- `243UT245X0` — Siti Saimah Binti Abd Hamid
- `261UT240PM` — Lim Yee Chen

**Admins:** `ADM001` (Dr. Tan Wei Ming), `ADM002` (Ms. Lim Su Yin)

---

## 🧪 Demo Walkthrough (for the project presentation)

A suggested script that exercises every business rule in under 5 minutes:

1. **Log in** as `253UT256KY` / `password123`. The dashboard shows 0 credit hours and the team's name.
2. **Course Registration** → Click *Enrol* on **TAI2222** (Machine Learning). Note that the prereq badge says *met* because the demo data marks TCS1011 as completed.
3. **Enrol** in **TSE6223**, **TDS3851**, and **TIB2003**. Watch the credit-hour bar fill — you should be at 12/22.
4. Try to enrol in **TAI3001** (Advanced Neural Networks). It's **blocked** — prerequisite TAI2233 not met. The badge explains why.
5. **Drop trick:** Log out, log in as `253UT256JW`. Enrol in **TAI3001** (it has quota=2, so seats are tight). Then `243UT245X0` and `261UT240PM` do the same — the last one gets **waitlisted at position #1**.
6. The waitlisted student visits **My Waitlist** to confirm position #1, then **261UT240PM** stays logged in.
7. Switch back to `253UT256JW`, go to **Add/Drop**, and **drop** TAI3001. Flash message: *"Dropped TAI3001 successfully. Promoted [261UT240PM] from the waitlist."*
8. **261UT240PM** refreshes — now shows enrolled, no longer waitlisted.
9. **Registration Slip** → click *Print / Save as PDF*. The slip prints cleanly, sidebar hidden.
10. **Log in as admin** (`ADM001`). Visit **Reports**, download enrollments CSV. Open in Excel to verify status column.

---

## 🔒 Security Summary

| Threat | Defence |
|---|---|
| SQL injection | Every query uses PDO prepared statements with parameter binding; SQL is centralised in `models/` for audit |
| XSS | All untrusted output goes through `e()` (`htmlspecialchars` with `ENT_QUOTES`) |
| CSRF | One-time-per-session token in `$_SESSION['_csrf']`, required for every POST; `hash_equals` for timing-safe comparison |
| Session fixation | `session_regenerate_id(true)` immediately after login |
| Privilege escalation by URL | `require_student()` / `require_admin()` at the top of every protected page |
| Password storage | `password_hash(PASSWORD_BCRYPT)` (cost 12 by default) — verified with `password_verify` |
| Race condition on last seat | `BEGIN TRANSACTION` + `SELECT ... FOR UPDATE` on the courses row |
| Information disclosure via errors | Generic flash messages for users; `PDO::ERRMODE_EXCEPTION` in dev, log-and-friendly-message pattern |

---

## 🧯 Troubleshooting

**"Class 'Database' not found"** — confirm `includes/bootstrap.php` is loaded first; every page already does `require_once __DIR__ . '/../includes/bootstrap.php'`.

**"Access denied for user 'root'"** — edit `includes/db_credentials.php` to match your XAMPP MySQL password.

**"Session expired (CSRF token invalid)"** — your session was wiped (server restart, cookie cleared); just log in again.

**Login fails immediately** — re-import `database/ses_setup.sql`; the seed data uses bcrypt hashes of `password123`.

**Quota changed but isn't applying** — admin/courses.php blocks lowering quota below current `enrolled_count`. Drop students first.

---

## 📁 File map for marking

If the marker asks "show me where you do X", here's the answer:

- **MVC separation** — `models/` (data), `controllers/` (logic), `student/` + `admin/` (controller-views), `includes/header.php` (layout)
- **Prepared statements** — every `prepare()` call across `models/*.php`
- **Prerequisite check** — `controllers/EnrollmentController.php::checkPrerequisitesMet()`
- **Credit-hour cap** — `controllers/EnrollmentController.php::checkCreditCap()` + `config/config.php`
- **Waitlist promotion** — `models/EnrollmentModel.php::drop()` (the `$wasEnrolled` branch)
- **CSRF token** — `controllers/AuthController.php::csrfField()` and `verifyCsrf()`
- **Registration slip** — `student/slip.php` (note the `@media print` block)
- **Atomic enrolment** — `models/EnrollmentModel.php::enroll()` (the `FOR UPDATE` lock + transaction)

---

## 🙋 Team

| Name                          | Student ID    | Programme |
|-------------------------------|---------------|-----------|
| Andrew Lim Zi Fei             | 253UT256KY    | AI        |
| Desmond Choi Lip Sheng        | 253UT256JW    | AI        |
| Siti Saimah Binti Abd Hamid   | 243UT245X0    | AI        |
| Lim Yee Chen                  | 261UT240PM    | AI        |

Submitted for **TSE6223 Software Engineering Fundamentals**, Trimester 2, 2026 / 2027.
