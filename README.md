# NVDIA Staff Leave Application System

## Project Description
This is a web-based Staff Leave Application System developed using HTML, CSS, JavaScript, PHP, and MySQL. The project now uses a normalized role-based access control database design. The four login roles are Staff, Superior, HR, and Admin. Each role has different page permissions and actions.

## Updated Database Design
The previous version stored `role` and `department` as text inside the `staff` table. This version separates them into their own tables so information is shared consistently across the system.

| Table | Purpose |
|---|---|
| `role` | Stores Staff, Superior, HR, and Admin roles with permission flags. |
| `department` | Stores department records such as Administration, IT, HR, Finance, and Operations. |
| `staff` | Stores login accounts and links each user to one role and one department. |
| `superior` | Stores extra identity records for users with the Superior role. |
| `hr` | Stores extra identity records for users with the HR role. |
| `admin` | Stores extra identity records for users with the Admin role. |
| `leave_application` | Stores leave requests, status, approval result, and superior remark. |
| `email_log` | Stores generated approval/rejection email notification records. |

## Role Permission Logic

| Role | Can See / Do |
|---|---|
| Staff | Dashboard, My Profile, Apply Leave, Leave Status, Leave History |
| Superior | Dashboard, My Profile, Applications by Date, Applications by Staff, Email Logs |
| HR | Dashboard, My Profile, Manage Staff, Add Staff |
| Admin | Full access to Staff, Superior, HR, and Admin functions |

## Important Relationship Rules
- Every Staff account must be assigned to one Superior or Admin through `staff.superior_id`.
- Superior, HR, and Admin accounts do not need a superior.
- When HR/Admin changes a user's role, the system synchronizes the related `superior`, `hr`, or `admin` table automatically.
- When a superior/admin account is deleted, existing subordinates are reassigned to another available superior/admin before deletion.
- Staff password values are stored using PHP `password_hash()` instead of plain text.
- Login uses `password_verify()` and also supports old plain-text passwords one time, then upgrades them to a hash automatically.

## Technology Used
- HTML
- CSS
- JavaScript
- PHP
- MySQL / MariaDB
- XAMPP
- phpMyAdmin

## Main Features
- Login and logout
- Role-based sidebar and page access
- Staff leave application
- Leave status and leave history
- Superior approval and rejection
- Email approval/rejection notification record
- HR staff management
- Add, update, and safely delete staff records
- Department and role normalization
- Staff-superior relationship validation
- Prepared SQL statements for safer database operations

## Requirement Coverage

| Requirement ID | Requirement | Project File / Feature |
|---|---|---|
| REQ_101 | Apply Leave | `apply_leave.php` |
| REQ_102 | Check Leave Approval Status | `leave_status.php` |
| REQ_103 | View Leave History | `leave_history.php` |
| REQ_201 | Show list of leave application by date | `leave_list.php?view=date` |
| REQ_202 | Show list of leave application by staff | `leave_list.php?view=staff` |
| REQ_203 | Approve Leave Application | `leave_decision.php?action=approve` |
| REQ_204 | Reject Leave Application | `leave_decision.php?action=reject` |
| REQ_205 | Email Leave Approval/Reject Status | `email_log.php` and `email_log` table |
| REQ_301 | Show list of Staff | `staff_list.php` |
| REQ_302 | Add Staff | `add_staff.php` |
| REQ_302 | Delete Staff | `delete_staff.php` |
| REQ_303 | Update Staff Information | `update_staff.php` |
| REQ_401 | Sign in | `login.php` |
| REQ_402 | Sign out | `logout.php` |

## Project File Structure

| File | Purpose |
|---|---|
| `core.php` | Database connection, session handling, common functions, prepared query helpers, permission checking, header, footer, and JavaScript validation |
| `login.php` | Login page and login processing |
| `dashboard.php` | Main dashboard after login |
| `apply_leave.php` | Staff leave application form |
| `leave_status.php` | Staff leave status page |
| `leave_history.php` | Staff leave history page |
| `leave_list.php` | Superior leave review by date or by staff |
| `leave_decision.php` | Approve or reject leave application |
| `staff_list.php` | HR/Admin staff list page |
| `add_staff.php` | Add new staff page |
| `update_staff.php` | Update staff profile or staff information |
| `delete_staff.php` | Delete staff processing |
| `email_log.php` | Email notification record page |
| `database.sql` | Updated normalized MySQL database dump file |
| `las_db.sql` | Same updated database dump copy |
| `style.css` | System design and layout styling |

## Setup Steps in XAMPP
1. Start Apache and MySQL in XAMPP Control Panel.
2. Copy this project folder into `C:\xampp\htdocs\`.
3. Open phpMyAdmin.
4. Create or select database `las_db`.
5. Import `database.sql`.
6. Open the project in your browser:

```text
http://localhost/DSPD47_GROUP_2/
```

If you rename the folder, replace `DSPD47_GROUP_2` with your actual folder name.

## Default Demo Accounts

| Role | Email | Password |
|---|---|---|
| Admin | admin@las.com | admin123 |
| Superior | superior@las.com | 12345 |
| HR | hr@las.com | 12345 |
| Staff | staff@las.com | staff123 |
| Staff | ali@las.com | 12345 |
| Staff | jiahong@gmail.com | jh123 |
| Staff | kaize@gmail.com | kz123 |
| Staff | yiwei@gmail.com | yw123 |

## Database Import Note
This version changes the table structure. Import `database.sql` fresh in phpMyAdmin. If your old `las_db` contains previous test data, export a backup first because this SQL file drops and recreates the project tables.
