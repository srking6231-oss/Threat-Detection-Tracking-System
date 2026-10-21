TDTS PHP + MySQL dashboard

Files:
- index.php: live dashboard, table browsing/searching and generic add/edit/delete form for the 12 named tables
- action.php: performs insert/update/delete operations using prepared statements
- db.php: MySQL connection and metadata helper functions
- assets/style.css: dashboard styling

SETUP ON KALI LINUX / XAMPP / LAMP
1. Make sure Apache/PHP and MariaDB/MySQL are running.
2. Copy the tdts_php folder to your web server document root. On a typical Kali Apache setup, this is /var/www/html/tdts_php.
3. Open db.php and verify host, username, password, and database name. Defaults are localhost / root / blank password / cyber_track, matching the connection settings supplied earlier.
4. Confirm your database is named cyber_track and contains these tables:
   users, devices, security_analysis, threat_types, vulnerabilities, security_logs,
   alerts, incidents, incident_assignments, incident_updates, responses, audit_logs.
5. Open http://localhost/tdts_php/ in your browser.

IMPORTANT
- This dashboard uses table/column metadata from your existing database. It does not create or replace your database.
- It displays at most 200 records per selected table; search is across columns in that table.
- CRUD forms are generic. Foreign-key relationships and required business logic vary by schema, so some inserts may be rejected by MySQL until required related IDs are selected/provided. Use the exact IDs from your own tables.
- Delete is permanent and asks for confirmation. Test on a database backup first.
- This is intended for a local coursework project, not public production hosting. Do not expose a root account with a blank password or an unrestricted table editor to the public internet.
- No login, registration, or profile pages are included.
