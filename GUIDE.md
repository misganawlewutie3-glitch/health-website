# UoGSH Patient Information Management System (PIMS)
### Complete design, step-by-step build guide and code explanation
**Technologies:** HTML5 · CSS3 · JavaScript (vanilla) · PHP 8 (PDO) · MySQL/MariaDB

---

## PART 1 - ANALYSIS AND DESIGN

### 1.1 Problem statement
A large referral hospital such as University of Gondar Specialized Hospital (UoGSH) serves thousands of patients from the whole Amhara region and beyond. Paper cards cause: lost files, repeated registration of the same patient, slow searching, no link between OPD, laboratory, pharmacy and cashier, and no reliable statistics. PIMS replaces the paper card with one electronic patient file that every department can reach according to its role.

### 1.2 Objectives
1. Register each patient once and give a unique Medical Record Number (MRN).
2. Record every visit (vital signs, diagnosis, treatment) in the electronic file.
3. Connect the doctor, laboratory, pharmacy and cashier in one flow.
4. Support **24 departments** (admin can add, rename, disable more).
5. Protect patient privacy: login, roles, audit log, CSRF and XSS protection.
6. Give management reports (visits per department, top diagnoses, revenue).

### 1.3 Users (roles) and what each can open
| Role | Dashboard | Patients | Appointments | Consultations | Lab | Pharmacy | Billing | Reports | Users / Depts |
|---|---|---|---|---|---|---|---|---|---|
| admin | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ |
| doctor | ✔ | ✔ (view) | ✔ | ✔ | request/view | request/view | – | ✔ | – |
| nurse | ✔ | ✔ | ✔ | ✔ | – | – | – | – | – |
| receptionist | ✔ | ✔ register | ✔ | – | – | – | ✔ | – | – |
| lab technician | ✔ | – | – | – | ✔ enter result | – | – | – | – |
| pharmacist | ✔ | – | – | – | – | ✔ dispense | – | – | – |
| cashier | ✔ | – | – | – | – | – | ✔ | – | – |

This table is implemented exactly in the `PERMISSIONS` array of `includes/auth.php`.

### 1.4 The 24 departments
Outpatient (OPD), Emergency, Internal Medicine, General Surgery, Pediatrics, Gynecology & Obstetrics, Orthopedics, Ophthalmology, ENT, Dermatology, Psychiatry, Dental, Radiology, Laboratory, Pharmacy, Neurology, Cardiology, Oncology, Urology, Nephrology & Dialysis, Physiotherapy, ICU, Anesthesia, Nutrition.
They are rows in the `departments` table, so the list can be changed from the *Departments* page without touching code.

### 1.5 Patient flow (how the modules connect)
```
Reception ──► Register patient (MRN) ──► Book appointment / OPD
                                              │
                                              ▼
                               Doctor: Consultation (vitals, diagnosis)
                          ┌───────────────┼────────────────┬──────────────┐
                          ▼               ▼                ▼              ▼
                    Lab request      Prescription     Consultation fee   Admit / Refer
                          │               │                │
                   Lab enters result  Pharmacist       Cashier receives
                   (back to file)     dispenses        payment
                          └───────────────┴────────────────┘
                                          ▼
                              Everything is shown in the patient file
                              and counted in Reports
```

### 1.6 Database design (ERD in words)
- `departments` 1 ── N `users`, `appointments`, `visits`, `admissions`
- `patients` 1 ── N `appointments`, `visits`, `admissions`, `invoices`
- `visits` 1 ── N `lab_requests`, `prescriptions`
- `invoices` 1 ── N `payments`
- `users` is referenced by almost every table (who registered / treated / paid)
- `audit_log` stores every important action (who, what, when, IP)

Table summary: `departments`, `users`, `patients`, `appointments`, `admissions`, `visits`, `lab_requests`, `prescriptions`, `invoices`, `payments`, `audit_log` (11 tables, third normal form: no repeated department or patient text in other tables, only their ids).

### 1.7 Folder structure
```
uogsh-pims/
├── database/uogsh_pims.sql      ← all tables + 24 departments + admin user
├── config/config.php            ← settings + database connection
├── includes/
│   ├── auth.php                 ← session, roles, CSRF, helpers
│   ├── header.php               ← sidebar + top bar (every page)
│   └── footer.php               ← footer + loads JavaScript
├── assets/css/style.css         ← all design
├── assets/js/app.js             ← all browser behaviour
├── index.php  logout.php        ← login / logout
├── dashboard.php                ← statistics
├── patients.php  patient_form.php  patient_view.php
├── appointments.php  visits.php  visit_form.php
├── lab.php  pharmacy.php  billing.php
├── reports.php  departments.php  users.php
└── api_patient_search.php       ← JSON for live search
```

### 1.8 Technology roles (who does what)
| Layer | Technology | Job in this project |
|---|---|---|
| Structure | HTML5 | Pages, forms, tables |
| Appearance | CSS3 | Layout (flex/grid), colours, responsive, print |
| Behaviour in browser | JavaScript | Validation, live search, BMI/age, tabs, dynamic rows |
| Server logic | PHP | Login, permissions, validation, SQL, pages |
| Storage | MySQL | Persistent data |

---

## PART 2 - STEP-BY-STEP BUILD

### Step 1 - Install the tools
1. Install **XAMPP** (Apache + PHP + MySQL) - or WAMP/Laragon.
2. Start **Apache** and **MySQL** in the XAMPP control panel.
3. Install a code editor (VS Code).

### Step 2 - Create the project
Copy the folder `uogsh-pims` into `C:\xampp\htdocs\` (Linux: `/var/www/html/`).

### Step 3 - Create the database
1. Open `http://localhost/phpmyadmin` → **Import** → choose `database/uogsh_pims.sql` → **Go**.
   (or in a terminal: `mysql -u root -p < database/uogsh_pims.sql`)
2. The script creates database `uogsh_pims`, 11 tables, 24 departments and one admin user.

### Step 4 - Configure the connection
Open `config/config.php` and set `DB_USER` / `DB_PASS` if your MySQL is not the XAMPP default.

### Step 5 - Run
Open `http://localhost/uogsh-pims/` and log in:
- Username: `admin`
- Password: `Admin@1234`  → **change it immediately** from *Staff Users → Reset*.

### Step 6 - Create staff and test the workflow
1. *Staff Users* → create a receptionist, doctor, lab technician, pharmacist, cashier.
2. Log in as receptionist → *Patients → Register new patient*.
3. Book an appointment.
4. Log in as doctor → open the patient file → *New consultation*, add a lab test and a drug and a fee.
5. Lab technician enters the result; pharmacist dispenses; cashier receives payment.
6. Admin → *Reports*.

---

## PART 3 - FULL EXPLANATION OF EVERY FILE

### 3.1 `database/uogsh_pims.sql`
- `CREATE DATABASE ... utf8mb4` - supports Amharic (ፊደል) characters.
- `ENGINE=InnoDB` - needed for foreign keys and transactions.
- **Foreign keys** (`FOREIGN KEY ... REFERENCES`) guarantee that, for example, a visit cannot point to a patient that does not exist. `ON DELETE CASCADE` removes a patient's children if the patient is deleted; `ON DELETE SET NULL` keeps history when a staff user is removed.
- `ENUM(...)` limits a column to allowed values (e.g. role, status) so bad data cannot be stored.
- `UNIQUE` on `mrn` and `username` prevents duplicates. `INDEX` on name/phone makes searching fast.
- The default admin password is stored as a **bcrypt hash** (never plain text).

### 3.2 `config/config.php`
- `define()` creates global constants (hospital name, DB settings).
- `date_default_timezone_set('Africa/Addis_Ababa')` so times are Ethiopian time.
- `new PDO(...)` connects to MySQL. Options: exceptions on error, results as associative arrays.
- Errors are logged (`error_log`) but only a safe message is shown to the user, so passwords/paths never leak.

### 3.3 `includes/auth.php` (the security core)
| Part | What it does |
|---|---|
| `session_start()` with `httponly`, `samesite` | Remembers the logged-in user; JavaScript cannot steal the cookie |
| `PERMISSIONS` | Role table from section 1.3 |
| `e()` | `htmlspecialchars` - **prevents XSS**; used on every printed value |
| `require_login($module)` | Redirects to login, logs out after 30 min idle, returns 403 if role not allowed |
| `csrf_token / csrf_field / csrf_check` | A random token in every form; a forged form from another site is rejected |
| `flash / get_flash` | One-time "saved successfully" messages |
| `audit()` | Writes who/what/IP into `audit_log` |
| `next_mrn()` | Creates MRN like `UOG-2026-000123` |
| `age_from()` | Age from date of birth |

### 3.4 `includes/header.php` and `footer.php`
Header prints `<head>`, the sidebar (only menu items the user's role may open, using `can()`), and the top bar with name, role and Logout. Footer closes the tags and loads `app.js`. Every page does: `require auth` → `require_login('module')` → logic → `require header` → HTML → `require footer`.

### 3.5 `index.php` (login)
1. If already logged in → dashboard.
2. On POST: check CSRF → look up user with a **prepared statement** → `password_verify()` compares the typed password with the bcrypt hash.
3. `session_regenerate_id(true)` stops session-fixation attacks.
4. After 5 wrong tries in 5 minutes the form is blocked (basic brute-force protection).
5. Success is written to the audit log.

### 3.6 `dashboard.php`
Runs small `COUNT(*)` queries for the six cards, lists today's appointments (JOIN of three tables), and builds a bar chart of the busiest departments with pure CSS (`width:%` computed as `total / max * 100`).

### 3.7 `patients.php`
Search with `LIKE`, **pagination** (`LIMIT 15 OFFSET (page-1)*15`), total count for page numbers. Search text is bound as a parameter, so it cannot inject SQL.

### 3.8 `patient_form.php`
One file for *add* and *edit* (edit when `?id=` exists). Steps: collect fields → **server validation** (names, gender, date not in future, phone pattern, enum values) → empty optional fields stored as `NULL` → `INSERT` (new MRN) or `UPDATE` → audit → redirect with a success message. If errors occur, the form is shown again with the typed values.

### 3.9 `patient_view.php` (the electronic patient file)
Top card: name, MRN, age, address, and coloured alert badges (allergy, chronic illness, blood group, admitted). Five tabs: Visits, Lab, Prescriptions, Admissions, Billing. Also handles **admit** and **discharge/refer/death** actions. Each view is recorded in the audit log (privacy accountability).

### 3.10 `visit_form.php` (consultation)
Sections: visit details, vital signs, examination/diagnosis/plan, lab requests, prescription, fee. All inserts are inside **one transaction** (`beginTransaction → commit`, `rollBack` on error) so a consultation is never half-saved. Lab and drug inputs are arrays (`name="drug[]"`) looped with `foreach`. BMI is calculated live by JavaScript.

### 3.11 `visits.php`, `appointments.php`
- `visits.php`: filter by date range and department.
- `appointments.php`: books with validation (no past dates, **no double-booking of the same doctor/time**), shows a day schedule, changes status (Scheduled / Completed / Cancelled / No-Show). Live patient search and department → doctor filter use JavaScript.

### 3.12 `lab.php`, `pharmacy.php`, `billing.php`
- **Lab:** pending list with *Urgent first* (`ORDER BY priority='Urgent' DESC`); only lab/admin can save a result; status becomes *Completed*.
- **Pharmacy:** shows pending prescriptions and the patient's allergy warning; pharmacist presses *Dispense*.
- **Billing:** unpaid / paid / waived invoices; *Pay* inserts a `payments` row and marks the invoice paid inside a transaction with `SELECT ... FOR UPDATE` (so the same invoice cannot be paid twice by two cashiers); manual invoices; totals for today and outstanding.

### 3.13 `reports.php`, `departments.php`, `users.php`
- Reports: visits by department, top-10 diagnoses, gender, revenue by method, **CSV download** (open in Excel).
- Departments: add/edit/enable/disable (disabled departments disappear from dropdowns but history is kept).
- Users: create accounts (password rule: 8+ chars with letters and numbers), reset password, enable/disable; an admin cannot disable himself.

### 3.14 `api_patient_search.php`
Returns up to 8 matching patients as JSON for the live search box. It checks login and permission first.

### 3.15 `assets/css/style.css`
1. **Design tokens** (`:root` variables) - change `--primary` once to re-colour the whole system.
2. Reset/base typography.
3. Layout - flexbox: fixed sidebar + main column; sticky top bar.
4. Components - cards, stat tiles, buttons, badges, alerts, tabs, bar chart, pagination.
5. Forms - CSS grid (`repeat(auto-fit, minmax(230px, 1fr))`) makes forms adapt to any width.
6. Tables - hover rows, scroll wrapper.
7. Login page gradient card.
8. `@media (max-width: 860px)` hides the sidebar behind a ☰ button for phones/tablets; `@media print` hides menus so the patient file prints cleanly.

### 3.16 `assets/js/app.js`
Written with `data-*` attributes so each feature activates only on pages that need it:
| # | Feature | How it works |
|---|---|---|
| 1 | Mobile menu | toggles class `open` on the sidebar |
| 2 | Tabs | shows the panel whose id is in `data-tab` |
| 3 | Validation | on submit checks `required` / `pattern`, adds red border + message, `preventDefault()` if wrong |
| 4 | Confirm / Print | `confirm()` and `window.print()` |
| 5 | Show/hide password | switches input type |
| 6 | Age and BMI | Date arithmetic; BMI = kg ÷ m² with category |
| 7 | Add row | `cloneNode(true)` copies a lab/drug row |
| 8 | Live search | `fetch()` to the PHP JSON API, 250 ms debounce, `textContent` (XSS-safe) |
| 9 | Password meter, dept→doctor filter, auto-hide alerts, count-up numbers | small helpers |

> Important rule: **JavaScript validation is for convenience only.** A user can disable JavaScript, so PHP validates everything again.

---

## PART 4 - SECURITY CHECKLIST (what protects patient data)
| Threat | Defence in this system |
|---|---|
| SQL injection | PDO prepared statements everywhere |
| XSS (script in a name field) | `e()` on all output; `textContent` in JS |
| CSRF (forged form) | Token in every POST form |
| Weak passwords | bcrypt `password_hash`, strength rule, meter |
| Stolen/forgotten session | httponly cookie, regenerate id, 30-min idle timeout |
| Brute-force login | 5 attempts / 5 minutes lock |
| Unauthorised access | Role permission on every page + API |
| Cannot prove who did what | `audit_log` (login, view, update, pay, dispense...) |
| Half-saved data | Database transactions |

**Before real use:** run on HTTPS, set a strong MySQL password and a dedicated DB user (not `root`), change the admin password, back up the database daily, and get the hospital's ethics/IT approval for handling real patient data.

---

## PART 5 - TESTING (test cases to show in your report)
| # | Test | Expected |
|---|---|---|
| 1 | Login with wrong password | "Invalid username or password" |
| 2 | Cashier opens `patients.php` directly | 403 message |
| 3 | Register patient with empty name | Error list, nothing saved |
| 4 | Register with future birth date | Rejected |
| 5 | Book same doctor, same date/time twice | Second booking refused |
| 6 | Type `<script>alert(1)</script>` as a name | Shown as plain text, no popup |
| 7 | Consultation with 2 labs + 2 drugs + fee | All appear in file; invoice created |
| 8 | Lab saves result | Appears in patient file as Completed |
| 9 | Pharmacist dispenses | Status Dispensed |
| 10 | Pay an invoice twice (two browser tabs) | Second attempt does nothing |
| 11 | Idle 30 minutes | Redirect to login |
| 12 | Reports → Download CSV | File opens in Excel |

---

## PART 6 - FUTURE IMPROVEMENTS
Amharic interface (language files), SMS appointment reminders, ID-card barcode/QR scan, bed-management map, drug stock and expiry control, integration with DHIS2 for national reporting, role "department head" with department-only reports, automatic database backup, two-factor login.
