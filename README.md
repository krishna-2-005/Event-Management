# NMIMS Smart Event White Paper Workflow

Role-based event proposal, approval, and event publishing system for NMIMS Hyderabad.

This project manages the full lifecycle:
- White paper submission by club heads
- Multi-level approvals by academic and administrative roles
- Department/service clearances
- Query/reject/resubmit loops
- Event publishing, registrations, reports, and notifications

---

## Table of Contents

1. Overview
2. Key Features
3. Approval Workflow
4. Tech Stack
5. Project Structure
6. Requirements
7. Quick Setup (XAMPP)
8. Default Seed Accounts
9. How to Use (Role-Based)
10. Core Data Model
11. API/Utility Endpoints
12. Troubleshooting
13. Security Notes
14. Maintenance Notes

---

## Overview

The application is a PHP + MySQL system built for institutional event governance.

It supports:
- School-specific and club-specific ownership
- Role-aware queues for approvers and service teams
- Workflow state tracking (`approval_workflow_steps`, `department_tasks`)
- Notifications and activity logging
- Event creation after final approval

Repository layout:
- App root: `new_wp/`
- SQL schema + seed: `new_wp/event.sql`
- Repository root README (this file): `README.md`

---

## Key Features

### Proposal Lifecycle
- Submit complete white paper with:
  - Event details
  - Date/time/venue
  - Class impact
  - Service requirements
  - Budget rows (fixed + custom)
  - SPOC details
  - Declaration members
  - Attachments

### Workflow Engine
- Dynamic reviewer steps seeded into `approval_workflow_steps`
- Department/service tasks synchronized in `department_tasks`
- Role-aware queues separate "actionable now" from "upcoming" workload
- Handles:
  - Approve
  - Query raised
  - Reject with rejection counting and lock support
  - Resubmission flow

### Event Operations
- Approved proposals can be promoted to events
- Event calendar and listing views
- Student event registrations
- Post-event reporting and image uploads

### Admin Operations
- Manage schools, clubs, club members, venues, blocked dates
- Manage role assignments
- View all proposals and activity feed

### UX + Utilities
- Role-based dashboards and side navigation
- Notification center with unread counters
- White paper printable view (`view-white-paper.php`)

---

## Approval Workflow

### Level Mapping (Current Engine)

| Level | Roles |
|---|---|
| 1 | faculty_mentor |
| 2 | gs_treasurer |
| 3 | president_vc |
| 4 | school_head |
| 5 | it_team, housekeeping, food_admin, sports_dept |
| 6 | security_officer, rector, purchase_officer, accounts_officer, admin_office |
| 7 | deputy_registrar |
| 8 | deputy_director |
| 9 | director |

Source references:
- `new_wp/inc/workflow.php` -> `workflow_level_roles()`
- `new_wp/inc/app.php` -> `app_approval_level_for_role()`

### Main Workflow Chain

`faculty_mentor -> gs_treasurer -> president_vc -> school_head -> it_team -> housekeeping -> food_admin -> sports_dept -> security_officer -> rector -> purchase_officer -> accounts_officer -> admin_office -> deputy_registrar -> deputy_director -> director`

### Status Model

Proposal status uses `proposals.overall_status` (examples):
- `submitted`
- `under_*_review`
- `query_raised`
- `rejected_pending_response`
- `approved`
- `locked`

Step status uses `approval_workflow_steps.status` (examples):
- `pending`
- `approved`
- `query_raised`
- `rejected`
- `not_required`
- `resubmitted`
- `locked`

---

## Tech Stack

- PHP 8+ (uses strict typing + `match` expressions)
- MySQL / MariaDB
- mysqli + PDO (PDO used in parts like `index.php`)
- Vanilla JS + HTML/CSS
- XAMPP-friendly deployment

---

## Project Structure

```text
new_wp/
  config.php
  index.php
  login.php
  signup.php
  dashboard.php

  approvals.php
  department-tasks.php
  submit-proposal.php
  my-proposals.php
  proposal-details.php
  view-white-paper.php

  event-calendar.php
  student-events.php
  my-registrations.php
  post-event-report.php

  notifications.php
  activity-feed.php

  manage-schools.php
  manage-school-roles.php
  manage-users-roles.php
  manage-clubs.php
  manage-club-members.php
  manage-venues.php
  blocked-dates.php

  inc/
    app.php
    layout.php
    workflow.php

  assets/
    app.css
    images/

  uploads/
    club_logos/
    profile_images/
    proposals/

  event.sql
```

---

## Requirements

- XAMPP (Apache + MySQL)
- PHP extension set commonly bundled with XAMPP (`mysqli`, `pdo_mysql`)
- Browser access to local Apache host

Recommended:
- PHP CLI available at `D:/XAMPP/php/php.exe` for linting

---

## Quick Setup (XAMPP)

### 1) Place Project

Project is already at:
- `D:\XAMPP\htdocs\new_wp_last`

App directory:
- `D:\XAMPP\htdocs\new_wp_last\new_wp`

### 2) Start Services

Start in XAMPP Control Panel:
- Apache
- MySQL

### 3) Create/Import Database

- Open phpMyAdmin
- Create database: `event`
- Import file: `new_wp/event.sql`

### 4) Verify DB Config

`new_wp/config.php` defaults:
- host: `localhost`
- user: `root`
- password: empty
- database: `event`
- candidate ports: `3306`, `3307`

### 5) Open Application

Use one of:
- `http://localhost/new_wp_last/new_wp/`
- `http://localhost/new_wp_last/new_wp/login.php`

---

## Default Seed Accounts

The SQL dump includes many active users in `users`.

Verified default password for the common seeded hash:
- `123456`

Examples:
- Super Admin: `superadmin@college.com`
- Club Head: `clubhead@college.com`
- Faculty Mentor: `mentor@college.com`
- President VC: `president@college.com`
- GS Treasurer: `treasurer@college.com`
- School Head: `schoolhead@college.com`
- Admin Office: `adminoffice@college.com`
- Deputy Registrar: `dyregistrar@college.com`
- Director: `director@college.com`
- Student: `student@college.com`

Note:
- Some custom users in the dump have different password hashes.
- Use `generate_hash.php` to create new hashes if manually inserting users.

---

## How to Use (Role-Based)

### Club Head
- Submit white paper: `submit-proposal.php`
- Track proposals: `my-proposals.php`
- Respond to queries/rejections and resubmit
- Post event reports: `post-event-report.php`

### Main Approvers
Roles include faculty mentor through director.
- Review queue: `approvals.php`
- Queue split: **Pending Approvals** (actionable now) + **Upcoming Approvals** (waiting for earlier levels)
- Approve/query/reject based on active level
- Dashboard cards align with queue state: **Actionable Now**, **Upcoming**, **Approved**

### Department Roles
Roles include IT, housekeeping, security, purchase, accounts, sports, food admin.
- Work queue: `department-tasks.php`
- Queue split: **Actionable Service Tasks** + **Upcoming Tasks**
- Approve/reject/complete service clearances for actionable tasks

### Student
- Browse approved/upcoming events: `student-events.php`
- Register and track registrations: `my-registrations.php`

### Super Admin
- Full system management pages under `manage-*.php`
- System-wide proposal visibility and activity feed

---

## Core Data Model

Key tables:
- `users`
- `schools`
- `clubs`
- `venues`
- `proposals`
- `approval_workflow_steps`
- `department_tasks`
- `queries`
- `proposal_responses`
- `proposal_rejections`
- `notifications`
- `activity_logs`
- `events`
- `event_registrations`
- `event_reports`
- `event_images`

Schema and constraints are in:
- `new_wp/event.sql`

---

## API/Utility Endpoints

- `get_proposal.php` - proposal fetch helper
- `get_reports.php` - report fetch helper
- `mark_notification_read.php` - notification update helper
- `get_events.php` - legacy calendar helper

Important note on `get_events.php`:
- It references legacy fields (`program_chair_status`) and may need modernization to align with current workflow columns.

---

## Troubleshooting

### Database connection failed
- Ensure MySQL is running
- Check `new_wp/config.php` values
- Confirm DB name is `event`
- Confirm MySQL is on `3306` or `3307`

### Login fails for seed users
- Re-import `new_wp/event.sql`
- Ensure you are using the correct seeded email
- For common seed users, password is `123456`

### Empty queues for approvers/service roles
- Verify role assignments in `users`
- Verify `school_id` / `club_id` mappings
- Verify workflow rows exist in `approval_workflow_steps`
- Check for "Upcoming" items: tasks/approvals assigned to your role can appear there until earlier levels complete

### Check PHP syntax quickly

```powershell
& 'D:/XAMPP/php/php.exe' -l 'D:/XAMPP/htdocs/new_wp_last/new_wp/inc/workflow.php'
```

Project-wide lint (PowerShell):

```powershell
$php = 'D:/XAMPP/php/php.exe'
$root = 'D:/XAMPP/htdocs/new_wp_last/new_wp'
Get-ChildItem -Path $root -Recurse -Filter *.php | ForEach-Object { & $php -l $_.FullName }
```

---

## Security Notes

- Passwords are stored hashed (`password_hash` / `password_verify`)
- Session timeout is enforced in `app_session_timeout()`
- Role checks are enforced server-side (`app_require_login`, `app_require_roles`)
- Inputs are sanitized/validated in core flows (especially submission and auth)

Recommendations for production hardening:
- Move DB credentials to environment variables
- Enforce HTTPS
- Add CSRF tokens on all mutating forms
- Add rate limiting / login throttling
- Restrict file upload types and sizes further

---

## Maintenance Notes

- Main business logic: `new_wp/inc/workflow.php`
- Shared helpers and role/level maps: `new_wp/inc/app.php`
- Shared layout/navigation: `new_wp/inc/layout.php`

When changing approval flow:
1. Update role-level mappings in `app_approval_level_for_role()`
2. Update level role groups in `workflow_level_roles()`
3. Keep chain/stage/labels aligned (`app_main_workflow_chain()`, `app_stage_map()`, `app_workflow_role_sequence()`)
4. Re-test approvals queue and department tasks (actionable + upcoming)
5. Re-test dashboard counters for approver and department roles
6. Re-test query/reject/resubmit behavior

When changing schema:
- Keep `event.sql` updated with new tables/columns/indexes
- Ensure helper fallbacks (`app_table_exists`, `app_column_exists`) still make sense

---

## License

Internal institutional project. Add your preferred license policy here if needed.
