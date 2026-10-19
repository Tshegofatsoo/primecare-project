# PrimeCare — Staff API setup

The Android staff app talks to `api/staff/*` in this repo (PHP + MySQL, same database as the patient website).

## Local setup (XAMPP)
1. Copy this folder to `C:\xampp\htdocs\` and name it **primecarewebsite**.
2. In phpMyAdmin import `database/schema.sql`, then `database/staff_migration.sql` (run the migration once).
3. Seed: `C:\xampp\php\php.exe database\seed-staff.php --demo`
   (creates departments, 10 doctors, 10 staff logins, 12 patients, appointments, records, consultations,
   prescriptions, repeat requests and notifications — **every table ends with at least 10 rows**).
4. Enable `mod_rewrite` and `AllowOverride All` for htdocs so `api/staff/.htaccess` can pass the Authorization header.
5. Allow Apache through the firewall if a phone on Wi-Fi must reach it; browse to `http://<PC-IP>/primecarewebsite/api/staff/ping.php`.

## Hosting online (needed for the demo video)
Upload the folder to any PHP 8 + MySQL host (e.g. Azure App Service + Azure Database for MySQL, or a free PHP host),
edit `api/config/db.php` with the host's DB credentials, import the two SQL files, run the seed script
(or paste its output into the host's SQL console), and use `https://<your-domain>/` as the server address in the app.

## Configuration (environment variables, optional)
| Variable | Purpose | Default |
|---|---|---|
| `PRIMECARE_STAFF_CODE` | Clinic registration code required to create staff accounts | `PRIMECARE-2026` |
| `PRIMECARE_GOOGLE_CLIENT_ID` | Google **Web** OAuth client ID for single sign-on | *(blank = SSO off)* |

## Demo logins
admin@primecare.com / admin123 · reception@primecare.com / admin123 · doctor@primecare.com / doc123
(Dr Tendai Moyo) · lerato@ / sipho@ / ayesha@ / pieter@ / nomsa@ / kabelo@ / emma@primecare.com / doc123.
**Change these before real use.**

## Endpoints (all under `/api/staff/`)
`ping.php` · `auth/{login,register,google,logout,me,change-password,update-profile}.php` ·
`dashboard/{admin,doctor}.php` · `appointments/{today,checkin,assign,walkin}.php` · `queue/list.php` · `doctors/list.php` ·
`consultations/{start,save}.php` · `prescriptions/{create,list}.php` · `followups/create.php` ·
`repeat-requests/{list,decide}.php`. All except ping/login/register/google need `Authorization: Bearer <token>`.

## Quick check
```
curl -X POST http://localhost/primecarewebsite/api/staff/auth/login.php -H "Content-Type: application/json" -d "{\"email\":\"admin@primecare.com\",\"password\":\"admin123\"}"
```
