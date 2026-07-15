# Admin Employee Profile Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a linked admin employee profile with Monday–Sunday metrics, assigned tasks, daily attendance/task-time sessions, and safe direct corrections of completed sessions.

**Architecture:** Keep timestamp validation in a focused correction service and weekly grouping in a pure view builder so both can be unit tested without HTTP or a database. Add employee-scoped repository queries and thin AdminController routes, render one dedicated Twig workspace, and centralize employee-name linking in a reusable Twig partial.

**Tech Stack:** PHP 8.4, Symfony 7.4, Doctrine ORM, Twig, CSS, PHPUnit 13, Docker Compose.

## Global Constraints

- All new pages and writes require `ROLE_SUPER_ADMIN` through the existing AdminController restriction.
- Weeks run Monday 00:00 through the following Monday 00:00 in `Europe/Berlin`.
- Corrections overwrite timestamps directly and do not create audit history.
- Only completed attendance and task-time sessions are editable.
- Corrected sessions must end after they start and remain within one local calendar day.
- Attendance corrections must continue to contain every existing completed break.
- Employee self-service behavior and permissions must not change.
- No database migration is required.

---

### Task 1: Completed-session correction service

**Files:**
- Create: `src/Service/TimeEntryCorrectionService.php`
- Create: `tests/Service/TimeEntryCorrectionServiceTest.php`

**Interfaces:**
- Consumes: `AttendanceEntry`, `TaskTimeEntry`, `BreakEntry`, and `Y-m-d\TH:i` local form values.
- Produces: `correctAttendance(AttendanceEntry $entry, string $startedAt, string $endedAt): void` and `correctTaskTime(TaskTimeEntry $entry, string $startedAt, string $endedAt, ?string $note): void`.
- Throws: `InvalidArgumentException` with user-facing validation text; entities remain unchanged when validation fails.

- [ ] **Step 1: Write failing service tests**

Create tests covering successful attendance/task correction plus open-entry, end-before-start, cross-day, invalid-format, and break-outside-attendance rejection:

```php
public function testCorrectsCompletedAttendanceWhenBreaksRemainInsideRange(): void
{
    $entry = (new AttendanceEntry())
        ->setCheckInAt(new \DateTimeImmutable('2026-07-14 08:00'))
        ->setCheckOutAt(new \DateTimeImmutable('2026-07-14 17:00'));
    $entry->addBreak((new BreakEntry())
        ->setStartedAt(new \DateTimeImmutable('2026-07-14 12:00'))
        ->setEndedAt(new \DateTimeImmutable('2026-07-14 12:30')));

    (new TimeEntryCorrectionService())->correctAttendance($entry, '2026-07-14T08:15', '2026-07-14T16:45');

    self::assertSame('08:15', $entry->getCheckInAt()->format('H:i'));
    self::assertSame('16:45', $entry->getCheckOutAt()?->format('H:i'));
}
```

For every invalid case, capture the original timestamps, assert `InvalidArgumentException`, then assert both original values are unchanged.

- [ ] **Step 2: Run the service test and verify RED**

Run: `php bin/phpunit tests/Service/TimeEntryCorrectionServiceTest.php`

Expected: FAIL because `TimeEntryCorrectionService` does not exist.

- [ ] **Step 3: Implement parse-then-apply validation**

Implement a private parser using an explicit timezone and strict format validation:

```php
private function parseRange(string $startedAt, string $endedAt): array
{
    $timezone = new \DateTimeZone('Europe/Berlin');
    $start = \DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $startedAt, $timezone);
    $startErrors = \DateTimeImmutable::getLastErrors();
    $end = \DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $endedAt, $timezone);
    $endErrors = \DateTimeImmutable::getLastErrors();
    if (!$start || !$end || ($startErrors !== false && array_sum($startErrors) > 0) || ($endErrors !== false && array_sum($endErrors) > 0)) {
        throw new \InvalidArgumentException('Enter valid start and end times.');
    }
    if ($end <= $start) {
        throw new \InvalidArgumentException('The end time must be after the start time.');
    }
    if ($start->format('Y-m-d') !== $end->format('Y-m-d')) {
        throw new \InvalidArgumentException('A corrected session must stay within one day.');
    }
    return [$start, $end];
}
```

In `correctAttendance`, reject a null checkout, parse first, verify every break is completed and falls between the proposed bounds, then set both attendance timestamps. In `correctTaskTime`, reject a null end, parse first, then set start, end, and note.

- [ ] **Step 4: Run the service tests and verify GREEN**

Run: `php bin/phpunit tests/Service/TimeEntryCorrectionServiceTest.php`

Expected: all correction tests pass.

- [ ] **Step 5: Commit the correction service**

```bash
git add src/Service/TimeEntryCorrectionService.php tests/Service/TimeEntryCorrectionServiceTest.php
git commit -m "feat: validate admin time corrections"
```

---

### Task 2: Monday-based employee week view and scoped queries

**Files:**
- Create: `src/Service/EmployeeWeekViewBuilder.php`
- Create: `tests/Service/EmployeeWeekViewBuilderTest.php`
- Modify: `src/Repository/TaskTimeEntryRepository.php`
- Modify: `src/Repository/TaskRepository.php`
- Create: `tests/Controller/AdminEmployeeProfileTest.php`

**Interfaces:**
- Consumes: selected `DateTimeImmutable`, lists of employee attendance entries and task-time entries.
- Produces: `range(DateTimeImmutable $selectedDate): array` with `start`, `end`, `end_exclusive`, and `selected_date`, plus `build(DateTimeImmutable $selectedDate, array $attendanceEntries, array $taskTimeEntries): array` containing that range, `days_worked`, `attendance_seconds`, `task_seconds`, and seven `days` records.
- Repository additions: `TaskTimeEntryRepository::findForUserBetween(User, DateTimeImmutable, DateTimeImmutable): array`, `TaskTimeEntryRepository::sumSecondsByTaskForUser(User): array<int,int>`, and `TaskRepository::findAssignedTo(User): array`.

- [ ] **Step 1: Write failing week-builder tests**

Test that Wednesday 15 July 2026 normalizes to Monday 13 July through Sunday 19 July, counts unique attendance dates, totals attendance/task seconds, and creates seven ordered day groups:

```php
$view = (new EmployeeWeekViewBuilder())->build(
    new \DateTimeImmutable('2026-07-15', new \DateTimeZone('Europe/Berlin')),
    [$mondayAttendance, $mondaySecondAttendance, $tuesdayAttendance],
    [$mondayTaskTime],
);
self::assertSame('2026-07-13', $view['start']->format('Y-m-d'));
self::assertSame('2026-07-19', $view['end']->format('Y-m-d'));
self::assertSame(2, $view['days_worked']);
self::assertCount(7, $view['days']);
```

- [ ] **Step 2: Run the builder test and verify RED**

Run: `php bin/phpunit tests/Service/EmployeeWeekViewBuilderTest.php`

Expected: FAIL because the builder does not exist.

- [ ] **Step 3: Implement the pure week builder**

Normalize with ISO weekday numbering:

```php
$local = $selectedDate->setTimezone(new \DateTimeZone('Europe/Berlin'))->setTime(0, 0);
$start = $local->modify(sprintf('-%d days', (int) $local->format('N') - 1));
$endExclusive = $start->modify('+7 days');
```

Expose the normalized values from `range()`. In `build()`, call `range()`, pre-create seven groups keyed by `Y-m-d`, attribute each entry by its local start date, sum `getWorkedSeconds()` and `getSeconds()`, count unique attendance keys, and return inclusive `end` as `$endExclusive->modify('-1 day')`.

- [ ] **Step 4: Add failing repository-query contract assertions**

In `AdminEmployeeProfileTest`, assert source contains `findForUserBetween`, `sumSecondsByTaskForUser`, `findAssignedTo`, `andWhere('t.employee = :employee')`, and `andWhere(':employee MEMBER OF t.assignees')`.

- [ ] **Step 5: Run the profile contract test and verify RED**

Run: `php bin/phpunit tests/Controller/AdminEmployeeProfileTest.php`

Expected: FAIL because the repository methods do not exist.

- [ ] **Step 6: Implement employee-scoped repository methods**

Reuse `AttendanceEntryRepository::findForUserBetween`. Add these concrete query shapes:

```php
public function findForUserBetween(User $employee, \DateTimeImmutable $from, \DateTimeImmutable $to): array
{
    return $this->createQueryBuilder('t')
        ->leftJoin('t.task', 'task')->addSelect('task')
        ->leftJoin('task.project', 'project')->addSelect('project')
        ->andWhere('t.employee = :employee')
        ->andWhere('t.startedAt >= :from')->andWhere('t.startedAt < :to')
        ->setParameter('employee', $employee)->setParameter('from', $from)->setParameter('to', $to)
        ->orderBy('t.startedAt', 'ASC')->getQuery()->getResult();
}

public function findAssignedTo(User $employee): array
{
    return $this->createQueryBuilder('t')
        ->leftJoin('t.project', 'project')->addSelect('project')
        ->leftJoin('t.parent', 'parent')->addSelect('parent')
        ->leftJoin('t.assignees', 'assignees')->addSelect('assignees')
        ->andWhere(':employee MEMBER OF t.assignees')->setParameter('employee', $employee)
        ->orderBy('project.name', 'ASC')->addOrderBy('t.createdAt', 'DESC')
        ->getQuery()->getResult();
}
```

For `sumSecondsByTaskForUser`, load the employee's entries with their tasks, loop over `TaskTimeEntry` objects, and add `getSeconds()` into an integer array keyed by `$entry->getTask()->getId()`. This avoids database-specific timestamp arithmetic.

- [ ] **Step 7: Run builder and repository tests**

Run: `php bin/phpunit tests/Service/EmployeeWeekViewBuilderTest.php tests/Controller/AdminEmployeeProfileTest.php`

Expected: all tests pass.

- [ ] **Step 8: Commit weekly aggregation**

```bash
git add src/Service/EmployeeWeekViewBuilder.php tests/Service/EmployeeWeekViewBuilderTest.php src/Repository/TaskTimeEntryRepository.php src/Repository/TaskRepository.php tests/Controller/AdminEmployeeProfileTest.php
git commit -m "feat: build employee weekly profile data"
```

---

### Task 3: Admin employee profile and correction routes

**Files:**
- Modify: `src/Controller/AdminController.php`
- Create: `templates/admin/employee_show.html.twig`
- Modify: `public/styles.css`
- Modify: `templates/base.html.twig`
- Modify: `tests/Controller/AdminEmployeeProfileTest.php`
- Modify: `tests/Controller/AdminTaskWorkspaceTest.php`

**Interfaces:**
- Consumes: Task 1 correction service, Task 2 builder and repository queries.
- Produces: `admin_employee_show`, `admin_employee_attendance_update`, and `admin_employee_task_time_update` routes plus the complete employee profile UI.

- [ ] **Step 1: Add failing route and template assertions**

Assert that the controller contains all three route names, `MapEntity`, ownership checks, CSRF IDs, and both correction calls. Assert that `employee_show.html.twig` contains `Days worked`, `Attendance hours`, `Task hours`, `Assigned tasks`, `Daily time`, both update route names, `type="week"`, `entry.checkOutAt`, and `entry.endedAt`.

- [ ] **Step 2: Run the profile test and verify RED**

Run: `php bin/phpunit tests/Controller/AdminEmployeeProfileTest.php`

Expected: FAIL because the routes and template are absent.

- [ ] **Step 3: Implement the GET profile route**

Add imports for `MapEntity`, `EmployeeWeekViewBuilder`, and `TimeEntryCorrectionService`. Parse `week` as `YYYY-Www`; invalid input uses today. Call `$weekBuilder->range($selectedDate)`, query employee entries with its `start` and `end_exclusive`, then call `build()` with those entries. Render:

```php
return $this->render('admin/employee_show.html.twig', [
    'employee' => $employee,
    'week' => $weekView,
    'tasks' => $tasks->findAssignedTo($employee),
    'task_totals' => $taskTimeEntries->sumSecondsByTaskForUser($employee),
]);
```

Reject users whose role is not `User::ROLE_EMPLOYEE` with a 404.

- [ ] **Step 4: Implement both POST correction routes**

Use explicit mappings `#[MapEntity(id: 'employeeId')] User $employee` and `#[MapEntity(id: 'entryId')] AttendanceEntry|TaskTimeEntry $entry`. Verify entry ownership, guard CSRF with `attendance_correction_{entryId}` or `task_time_correction_{entryId}`, call the service inside `try/catch (InvalidArgumentException $exception)`, flash the exact error, and redirect with submitted `week` preserved.

- [ ] **Step 5: Build the profile template**

Render the profile header, four metric cards, week selector, assigned task table, and seven daily groups. The correction form structure is:

```twig
{% if entry.checkOutAt %}
    <details class="admin-employee-correction">
        <summary>Edit</summary>
        <form method="post" action="{{ path('admin_employee_attendance_update', {employeeId: employee.id, entryId: entry.id}) }}">
            <input type="hidden" name="_token" value="{{ csrf_token('attendance_correction_' ~ entry.id) }}">
            <input type="hidden" name="week" value="{{ week.start|date('o-\\WW') }}">
            <input type="datetime-local" name="started_at" value="{{ entry.checkInAt|date('Y-m-d\\TH:i') }}" required>
            <input type="datetime-local" name="ended_at" value="{{ entry.checkOutAt|date('Y-m-d\\TH:i') }}" required>
            <button>Save</button>
        </form>
    </details>
{% else %}
    <span class="badge badge-success">Running</span>
{% endif %}
```

Use the equivalent task-time route, CSRF ID, `startedAt`, `endedAt`, and note textarea. Use `duration` for seconds and link tasks to `admin_tasks` with `project` and `task` query parameters.

- [ ] **Step 6: Add responsive profile styling and bump cache key**

Add the shared layout primitives below, plus matching border, spacing, typography, and dark-theme-safe colors using existing variables:

```css
.admin-employee-metrics { display: grid; gap: 14px; grid-template-columns: repeat(4, minmax(0, 1fr)); }
.admin-employee-days { display: grid; gap: 14px; }
.admin-employee-session { align-items: center; display: grid; gap: 12px; grid-template-columns: minmax(180px, 1fr) repeat(3, minmax(100px, auto)) auto; }
.admin-employee-correction form { display: grid; gap: 10px; grid-template-columns: repeat(2, minmax(180px, 1fr)) auto; }
@media (max-width: 900px) { .admin-employee-metrics { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
@media (max-width: 620px) { .admin-employee-metrics, .admin-employee-session, .admin-employee-correction form { grid-template-columns: 1fr; } }
```

Change `waldbyte-hr-21` to `waldbyte-hr-22` and update the cache-key assertion.

- [ ] **Step 7: Run profile, task-workspace, and Twig checks**

```bash
php bin/phpunit tests/Controller/AdminEmployeeProfileTest.php tests/Controller/AdminTaskWorkspaceTest.php
php bin/console lint:twig templates/admin/employee_show.html.twig templates/base.html.twig
```

Expected: tests and Twig lint pass.

- [ ] **Step 8: Commit the employee workspace**

```bash
git add src/Controller/AdminController.php templates/admin/employee_show.html.twig public/styles.css templates/base.html.twig tests/Controller/AdminEmployeeProfileTest.php tests/Controller/AdminTaskWorkspaceTest.php
git commit -m "feat: add admin employee profile workspace"
```

---

### Task 4: Reusable employee-name links across admin-visible pages

**Files:**
- Create: `templates/partials/_employee_name.html.twig`
- Modify: `templates/admin/employees.html.twig`
- Modify: `templates/admin/reports.html.twig`
- Modify: `templates/admin/_task_detail_panel.html.twig`
- Modify: `templates/documents/index.html.twig`
- Modify: `templates/directory/index.html.twig`
- Modify: `templates/task/show.html.twig`
- Modify: `tests/Controller/AdminEmployeeProfileTest.php`

**Interfaces:**
- Consumes: a `user` variable and optional `class` variable.
- Produces: an `admin_employee_show` link only for a super-admin viewer and an employee reference; otherwise escaped plain name text.

- [ ] **Step 1: Add failing link-partial assertions**

Assert the partial contains `ROLE_SUPER_ADMIN`, `ROLE_EMPLOYEE`, and `admin_employee_show`. Assert each listed template includes `partials/_employee_name.html.twig`.

- [ ] **Step 2: Run the profile test and verify RED**

Run: `php bin/phpunit tests/Controller/AdminEmployeeProfileTest.php`

Expected: FAIL because the partial and includes are missing.

- [ ] **Step 3: Implement the reusable partial**

```twig
{% set link_class = class|default('employee-profile-link') %}
{% if user and is_granted('ROLE_SUPER_ADMIN') and user.role == constant('App\\Entity\\User::ROLE_EMPLOYEE') %}
    <a class="{{ link_class }}" href="{{ path('admin_employee_show', {id: user.id}) }}">{{ user.fullName }}</a>
{% elseif user %}
    <span class="{{ link_class }}">{{ user.fullName }}</span>
{% else %}
    <span class="{{ link_class }}">Deleted user</span>
{% endif %}
```

- [ ] **Step 4: Replace employee-name text in admin-visible contexts**

Use the partial in Users and Reports plus task assignees, creators, comment authors, status actors, activity actors, document owners/uploaders, and directory cards. Preserve avatar, job title, and punctuation around each include.

- [ ] **Step 5: Run link tests and Twig lint**

```bash
php bin/phpunit tests/Controller/AdminEmployeeProfileTest.php
php bin/console lint:twig templates
```

Expected: tests and all Twig lint pass.

- [ ] **Step 6: Commit linked employee names**

```bash
git add templates/partials/_employee_name.html.twig templates/admin/employees.html.twig templates/admin/reports.html.twig templates/admin/_task_detail_panel.html.twig templates/documents/index.html.twig templates/directory/index.html.twig templates/task/show.html.twig tests/Controller/AdminEmployeeProfileTest.php
git commit -m "feat: link employee names to admin profiles"
```

---

### Task 5: Full verification and rendered correction checks

**Files:**
- Verify only; modify earlier files only if verification exposes a defect.

**Interfaces:**
- Consumes: all prior tasks.
- Produces: a rebuilt application with verified admin profile navigation and correction flows.

- [ ] **Step 1: Run the full local quality gate**

```bash
php bin/phpunit
php bin/console lint:twig templates
php bin/console lint:container
composer validate --no-check-publish
git diff --check
```

Expected: every command exits 0.

- [ ] **Step 2: Rebuild the application**

Run: `docker compose up -d --build app`

Expected: app and database containers are running and healthy.

- [ ] **Step 3: Verify rendered profile and name links**

Authenticate as `admin@example.com`, open an employee profile for the current ISO week, and verify HTTP 200 plus all four metric labels, seven day groups, the assigned task table, and employee links from Users and Reports.

- [ ] **Step 4: Verify corrections with temporary fixtures**

Create temporary completed attendance and task-time entries. Submit valid corrections with their rendered CSRF tokens and confirm database timestamps changed. Submit an attendance range excluding its break and confirm no change. Confirm open sessions have no edit forms. Delete fixtures afterward.

- [ ] **Step 5: Check cleanup and repository state**

Run `git status --short` and query for temporary `codex-%@example.com` users. Expected: no uncommitted files and no temporary users.
