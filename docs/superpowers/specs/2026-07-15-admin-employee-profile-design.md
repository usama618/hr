# Admin Employee Profile Design

## Goal

Give administrators a single employee-focused workspace reachable by clicking employee names throughout admin-visible portal pages. The workspace shows the employee's current-week attendance and task performance, assigned tasks, and editable completed attendance and task-time sessions.

## Scope

This feature applies only to authenticated super administrators. Employee self-service pages and permissions remain unchanged.

The implementation will:

- add a read-only employee profile overview with administrative correction controls;
- make employee names link to that profile throughout admin-facing views;
- calculate weekly metrics using Monday through Sunday in the Europe/Berlin timezone;
- show assigned tasks and employee-specific tracked time;
- allow direct correction of completed attendance sessions and completed task-time sessions;
- allow navigating to earlier or later weeks.

It will not add correction audit history, approval workflows, payroll calculations, or changes to the employee's own profile editor.

## Navigation and Routes

The primary page is `GET /admin/employees/{id}`, named `admin_employee_show`.

Employee-name links in admin-visible templates point to this route. The link scope includes:

- the Users list;
- attendance, leave, and task-time reports;
- admin task details, task comments, status history, activities, and time logs;
- document owner and uploader references when the referenced user is an employee;
- the directory when viewed by a super administrator.

Super-admin names are not linked to an employee profile. A small reusable Twig partial renders either an employee-profile link or plain text, preventing each page from duplicating role checks and URL construction.

The employee profile provides an `Edit User` action linking to the existing `admin_employee_edit` route and a back link to the Users list.

Correction endpoints are admin-only POST routes:

- `POST /admin/employees/{employeeId}/attendance/{entryId}` updates one attendance entry;
- `POST /admin/employees/{employeeId}/task-time/{entryId}` updates one task-time entry.

Each endpoint verifies CSRF, verifies the entry belongs to the employee in the URL, validates the submitted timestamps, saves the correction, and redirects back to the same employee and selected week.

## Page Layout

The page follows the existing Waldbyte visual system and contains four areas.

### Profile Header

The header shows the employee avatar, full name, job title, email, active status, project count, and skill badges. It includes `Edit User` and `Back to Users` actions.

### Weekly Summary

Four compact metric cards show:

- **Days worked:** the number of unique local calendar dates in the selected Monday–Sunday week that contain an attendance entry;
- **Attendance hours:** total worked seconds from attendance entries, using check-in to check-out minus recorded breaks;
- **Task hours:** total seconds from the employee's task-time entries that start during the selected week;
- **Assigned tasks:** the number of tasks currently assigned to the employee.

An HTML week input selects any date in a week. The server normalizes it to that week's Monday and displays the inclusive Monday–Sunday range. Invalid or missing week input falls back to the current week.

### Assigned Tasks

The task table lists all tasks currently assigned to the employee, including recursive subtasks. Each row shows project, task title, status, priority, schedule, and the selected employee's total tracked time on that task. Task titles link to the existing admin task detail experience.

### Daily Time

The selected week's seven days are displayed as grouped day sections. Each section shows its daily attendance total and task-time total, followed by:

- attendance entries with check-in, check-out, break duration, and worked duration;
- task-time entries with project, task, start, end, duration, and note.

Completed entries have an inline `Edit` control that expands a compact correction form. Open attendance or task sessions are visible with a `Running` badge but have no correction form.

Empty days remain visible with a concise `No tracked time` state so the full week can be reviewed at a glance.

## Data Access and Aggregation

Repository methods provide employee-scoped range queries rather than filtering company-wide collections in the controller:

- attendance entries for one employee between Monday 00:00 inclusive and the following Monday 00:00 exclusive;
- task-time entries for one employee within the same range, with task and project eager-loaded;
- all assigned tasks for one employee, with project and time entries available for the employee-specific totals.

A focused service or private builder converts these records into the seven daily groups and weekly totals. Day grouping uses Europe/Berlin local dates. An entry is attributed to the date on which it starts, matching the existing reporting model.

Attendance duration continues to use the entity's `getWorkedSeconds()` calculation. Task duration continues to use `TaskTimeEntry::getSeconds()`.

## Correction Rules

Corrections overwrite the stored timestamps directly and do not create an audit record, as requested.

Both attendance and task-time corrections require:

- a valid CSRF token;
- an entry that belongs to the employee shown in the route;
- a completed entry with an existing end timestamp;
- valid local date-time values;
- an end strictly later than the start;
- start and end on the same local calendar date;
- a maximum corrected session length of 24 hours.

Attendance corrections update check-in and check-out. Existing break records and their timestamps are preserved, and the form shows the resulting break deduction. If a corrected attendance range would place an existing break outside the session, validation rejects the change and explains that the attendance range must contain every recorded break.

Task-time corrections update start, end, and the optional note. The task and employee associations cannot be changed from this screen.

Invalid submissions do not persist partial changes. They add an error flash and redirect back to the selected week. Successful corrections add a success flash.

## Security

The existing class-level `ROLE_SUPER_ADMIN` restriction protects all new AdminController routes. Correction endpoints additionally check ownership of the entry against the route employee to prevent changing another employee's record through an altered URL.

All writes use POST and CSRF protection. No correction controls are rendered for open sessions.

## Testing

Automated coverage will verify:

- the employee profile route and template expose the four metrics, week selector, task table, and daily time groups;
- weekly boundaries normalize to Monday–Sunday;
- attendance and task queries are employee-scoped;
- employee names use the shared profile-link partial in admin-visible templates;
- valid completed attendance and task-time entries can be corrected;
- entries belonging to another employee cannot be changed;
- open entries cannot be changed;
- end-before-start, cross-day, and attendance-break boundary violations are rejected;
- existing admin and employee task workspace behavior remains green.

Rendered-page verification will authenticate as a super administrator, open an employee profile, confirm weekly data appears, and exercise one attendance and one task-time correction against temporary fixtures before restoring or deleting those fixtures.
