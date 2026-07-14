# Project Task Workspace Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build a project-filtered task workspace with unlimited nested subtasks, multiple assignees, rich task metadata, and an in-page task management panel.

**Architecture:** Extend `Task` as the hierarchy root and keep supporting concerns in focused Doctrine entities and services. Render the workflow with Symfony/Twig and progressively enhance tree expansion and the accessible detail dialog with small vanilla-JavaScript modules embedded in the existing base asset. Preserve normal URL/form behavior as the non-JavaScript path.

**Tech Stack:** PHP 8.2+, Symfony 7.4, Doctrine ORM/migrations, Twig, vanilla JavaScript, CSS, PHPUnit/Symfony test tools.

## Global Constraints

- Preserve unrelated dirty worktree files and existing HR behavior.
- Tasks and subtasks have identical capabilities and unlimited nesting.
- Child and parent tasks must always belong to the same project.
- Multiple assignees replace the single-assignee relation throughout permissions and notifications.
- Manager notes are admin-only.
- All mutations use POST plus CSRF validation.
- Uploaded task documents live outside `public/` and are limited to the existing 12 MB/type allowlist.
- UI supports light/dark themes, keyboard navigation, responsive layouts, and a no-JavaScript fallback.

---

### Task 1: Test Harness and Core Task Domain

**Files:**
- Modify: `composer.json`
- Modify: `composer.lock`
- Create: `phpunit.dist.xml`
- Create: `tests/bootstrap.php`
- Create: `tests/Entity/TaskTest.php`
- Modify: `src/Entity/Task.php`
- Modify: `src/Entity/User.php`
- Create: `migrations/Version20260714233000.php`

**Interfaces:**
- Produces: `Task::getParent(): ?Task`, `setParent(?Task): self`, `getChildren(): Collection`, `addChild(Task): self`, `removeChild(Task): self`.
- Produces: `Task::getAssignees(): Collection`, `addAssignee(User): self`, `removeAssignee(User): self`, `isAssignedTo(User): bool`.
- Produces: date, tag, reminder, recurrence, billing, note getters/setters and `getCompletionPercentage(): int`.

- [ ] **Step 1: Install the test runtime**

Run: `composer require --dev symfony/test-pack:^2.0 --no-interaction`

Expected: `phpunit/phpunit`, BrowserKit, and Symfony PHPUnit bridge are recorded in the lockfile.

- [ ] **Step 2: Add PHPUnit bootstrap/config and write failing entity tests**

Tests must assert recursive parent/child linking, assignment collection membership, case-insensitive tag normalization, date validation, allowed recurrence/billing fallbacks, and leaf-based completion. Representative completion test:

```php
public function testCompletionUsesAllLeafDescendants(): void
{
    $root = new Task();
    $branch = new Task();
    $done = (new Task())->setStatus(Task::STATUS_COMPLETED);
    $open = (new Task())->setStatus(Task::STATUS_TODO);
    $root->addChild($branch);
    $branch->addChild($done);
    $root->addChild($open);

    self::assertSame(50, $root->getCompletionPercentage());
}
```

- [ ] **Step 3: Verify RED**

Run: `php bin/phpunit tests/Entity/TaskTest.php`

Expected: FAIL because hierarchy, assignment collection, metadata, and completion methods do not exist.

- [ ] **Step 4: Implement the Task/User domain changes and migration**

Add self-referencing `parent`/`children`, many-to-many `assignees`, dates, JSON tags, reminder, recurrence, billing type, manager note, and nullable `sourceOccurrence`. The migration must create `task_assignees`, backfill from `tasks.assigned_to_id`, add indexes/constraints, then remove the old foreign key/column. Update `User::$tasks` to `ManyToMany(mappedBy: 'assignees')`.

- [ ] **Step 5: Verify GREEN and schema mapping**

Run: `php bin/phpunit tests/Entity/TaskTest.php && php bin/console doctrine:schema:validate --skip-sync`

Expected: tests pass and Doctrine mapping is valid.

- [ ] **Step 6: Commit**

```bash
git add composer.json composer.lock symfony.lock phpunit.dist.xml tests/bootstrap.php tests/Entity/TaskTest.php src/Entity/Task.php src/Entity/User.php migrations/Version20260714233000.php
git commit -m "feat: add hierarchical rich task model"
```

### Task 2: Supporting Task Records

**Files:**
- Create: `tests/Entity/TaskSupportingRecordsTest.php`
- Create: `src/Entity/TaskDocument.php`
- Create: `src/Entity/TaskDependency.php`
- Create: `src/Entity/TaskProblem.php`
- Create: `src/Entity/TaskStatusHistory.php`
- Create: `src/Entity/TaskActivity.php`
- Modify: `src/Entity/Task.php`
- Create: `migrations/Version20260714234500.php`

**Interfaces:**
- Produces: Task collections `getDocuments()`, `getDependencies()`, `getProblems()`, `getStatusHistory()`, `getActivities()`.
- Produces: immutable timestamps and fluent setters for each supporting entity.
- Produces: unique `(task_id, prerequisite_id)` dependency pairs.

- [ ] **Step 1: Write failing relationship/default tests**

Test bidirectional linking, default problem open state, status history values, activity metadata, document file metadata, and dependency self-rejection.

- [ ] **Step 2: Verify RED**

Run: `php bin/phpunit tests/Entity/TaskSupportingRecordsTest.php`

Expected: FAIL because supporting classes do not exist.

- [ ] **Step 3: Implement entities, Task collections, and migration**

Use `orphanRemoval: true` for records owned only by a task, `SET NULL` for deleted actors/uploaders, and cascade task deletion. Store activity metadata as JSON and timestamps as immutable datetimes.

- [ ] **Step 4: Verify GREEN**

Run: `php bin/phpunit tests/Entity/TaskSupportingRecordsTest.php && php bin/console doctrine:schema:validate --skip-sync`

Expected: PASS and valid mapping.

- [ ] **Step 5: Commit**

```bash
git add tests/Entity/TaskSupportingRecordsTest.php src/Entity/Task*.php migrations/Version20260714234500.php
git commit -m "feat: add task collaboration records"
```

### Task 3: Hierarchy, Dependency, Recurrence, and Audit Services

**Files:**
- Create: `tests/Service/TaskHierarchyServiceTest.php`
- Create: `tests/Service/TaskRecurrenceServiceTest.php`
- Create: `tests/Service/TaskActivityServiceTest.php`
- Create: `src/Service/TaskHierarchyService.php`
- Create: `src/Service/TaskRecurrenceService.php`
- Create: `src/Service/TaskActivityService.php`
- Create: `src/Repository/TaskRepository.php`
- Modify: `src/Entity/Task.php`

**Interfaces:**
- `TaskHierarchyService::assertValidParent(Task $task, ?Task $parent): void` throws `DomainException` for project mismatch/cycle.
- `TaskHierarchyService::buildTree(iterable $tasks): array` returns ordered `array<int, array{task: Task, depth: int, has_children: bool}>`.
- `TaskHierarchyService::assertValidDependency(Task $task, Task $prerequisite): void` rejects cross-project/self/cycles.
- `TaskRecurrenceService::createNextOccurrence(Task $task): ?Task` creates exactly one next instance for newly completed recurring tasks.
- `TaskActivityService::record(Task $task, ?User $actor, string $type, string $summary, array $metadata = []): TaskActivity`.
- `TaskRepository::findWorkspaceTasks(Project $project): array` returns all project tasks with assignees in a bounded query count.

- [ ] **Step 1: Write failing service tests**

Cover three-level tree depth/order, invalid parent cycles, dependency cycles, recurrence date advancement and no child/comment copying, duplicate occurrence prevention, and activity construction.

- [ ] **Step 2: Verify RED**

Run: `php bin/phpunit tests/Service`

Expected: FAIL because services/repository do not exist.

- [ ] **Step 3: Implement minimal services and repository**

Hierarchy traversal must maintain visited/active ID maps. Recurrence supports `daily`, `weekly`, and `monthly`, copies approved scalar/assignment fields, links `sourceOccurrence`, and returns `null` if an occurrence already exists.

- [ ] **Step 4: Verify GREEN**

Run: `php bin/phpunit tests/Service`

Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add tests/Service src/Service/TaskHierarchyService.php src/Service/TaskRecurrenceService.php src/Service/TaskActivityService.php src/Repository/TaskRepository.php src/Entity/Task.php
git commit -m "feat: add task hierarchy and recurrence services"
```

### Task 4: Forms and Project-Filtered Admin Workspace

**Files:**
- Create: `tests/Controller/AdminTaskWorkspaceTest.php`
- Modify: `src/Form/TaskFormType.php`
- Modify: `src/Controller/AdminController.php`
- Rewrite: `templates/admin/tasks.html.twig`
- Modify: `templates/admin/task_form.html.twig`
- Create: `templates/admin/_task_tree_rows.html.twig`

**Interfaces:**
- `AdminController::tasks(Request, ProjectRepository/EntityManager, TaskRepository, TaskHierarchyService): Response` selects a project from `?project=` and optional panel task from `?task=`.
- `TaskFormType` accepts options `locked_project`, `parent_task`, and `compact`.
- Workspace template variables: `projects`, `selected_project`, `task_rows`, `selected_task`, `task_form`.

- [ ] **Step 1: Write failing controller/render tests**

Test first-active-project fallback, valid project filtering, invalid project fallback, empty project state, nested row rendering, query-preserving Add/Edit links, and selected task panel context.

- [ ] **Step 2: Verify RED**

Run: `php bin/phpunit tests/Controller/AdminTaskWorkspaceTest.php`

Expected: FAIL against the current single table.

- [ ] **Step 3: Implement forms/controller/template**

Render project navigation and flattened recursive rows. Create/new/edit redirects preserve `project` and `task`. Parent context locks project and is validated through `TaskHierarchyService` before flush.

- [ ] **Step 4: Verify GREEN and Twig**

Run: `php bin/phpunit tests/Controller/AdminTaskWorkspaceTest.php && php bin/console lint:twig templates/admin`

Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add tests/Controller/AdminTaskWorkspaceTest.php src/Form/TaskFormType.php src/Controller/AdminController.php templates/admin/tasks.html.twig templates/admin/task_form.html.twig templates/admin/_task_tree_rows.html.twig
git commit -m "feat: build project task workspace"
```

### Task 5: Detail Panel Mutations and Supporting Features

**Files:**
- Create: `tests/Controller/AdminTaskDetailActionsTest.php`
- Create: `src/Controller/AdminTaskController.php`
- Create: `templates/admin/_task_detail_panel.html.twig`
- Create: `templates/admin/task_document_download.html.twig` only if an HTML error fallback is needed
- Modify: `config/services.yaml`

**Interfaces:**
- POST routes under `/admin/tasks/{id}/...` for quick status, comments, manual time logs, document upload/delete, dependencies, problems, and problem resolution.
- GET `/admin/task-documents/{id}/download` returns an authorized attachment.
- All redirects call a private `workspaceRedirect(Task $task, bool $keepPanel = true): RedirectResponse` producing `admin_tasks?project=<id>&task=<id>`.

- [ ] **Step 1: Write failing mutation tests**

Cover CSRF rejection, valid/invalid manual minutes, 12 MB/type upload policy, authorized download, project-local dependency validation, dependency cycle error, problem add/resolve, and redirect context.

- [ ] **Step 2: Verify RED**

Run: `php bin/phpunit tests/Controller/AdminTaskDetailActionsTest.php`

Expected: FAIL because routes and panel do not exist.

- [ ] **Step 3: Implement routes and detail partial**

Reuse the document MIME allowlist, store files in `var/task-documents`, record relevant activities, create status history only when status changes, and keep each tab's form independent.

- [ ] **Step 4: Verify GREEN and route/Twig integrity**

Run: `php bin/phpunit tests/Controller/AdminTaskDetailActionsTest.php && php bin/console debug:router admin_task --show-controllers && php bin/console lint:twig templates/admin`

Expected: tests pass; all routes point to the intended controllers; Twig is valid.

- [ ] **Step 5: Commit**

```bash
git add tests/Controller/AdminTaskDetailActionsTest.php src/Controller/AdminTaskController.php templates/admin/_task_detail_panel.html.twig config/services.yaml
git commit -m "feat: add task detail collaboration tools"
```

### Task 6: Multiple-Assignee Employee and Notification Flows

**Files:**
- Create: `tests/Service/NotificationServiceTest.php`
- Create: `tests/Controller/EmployeeTaskAccessTest.php`
- Modify: `src/Service/NotificationService.php`
- Modify: `src/Controller/EmployeeController.php`
- Modify: `src/Form/EmployeeTaskFormType.php`
- Modify: `templates/employee/dashboard.html.twig`
- Modify: `templates/employee/project_show.html.twig`
- Modify: `templates/task/show.html.twig`

**Interfaces:**
- `NotificationService::notifyTaskAssigned(Task $task, ?User $actor = null, iterable $previousAssignees = []): void` notifies newly added assignees only.
- Employee visibility and mutation checks use `Task::isAssignedTo(User)`.
- Employee form maps `assignees` with `multiple => true`.

- [ ] **Step 1: Write failing access/recipient tests**

Cover any-assignee visibility, creator visibility, unrelated denial, all-assignee status rights, actor exclusion, creator inclusion, and recipient de-duplication.

- [ ] **Step 2: Verify RED**

Run: `php bin/phpunit tests/Service/NotificationServiceTest.php tests/Controller/EmployeeTaskAccessTest.php`

Expected: FAIL because production code still calls the removed single-assignee API.

- [ ] **Step 3: Replace all single-assignee assumptions**

Update queries to join `t.assignees`, forms/templates to render collections, default employee-created assignment to the creator, and admin edit logic to compare assignee ID sets.

- [ ] **Step 4: Verify GREEN and scan for legacy calls**

Run: `php bin/phpunit tests/Service/NotificationServiceTest.php tests/Controller/EmployeeTaskAccessTest.php && ! rg "getAssignedTo|setAssignedTo|assignedTo" src templates`

Expected: PASS and no legacy assignment references.

- [ ] **Step 5: Commit**

```bash
git add tests/Service/NotificationServiceTest.php tests/Controller/EmployeeTaskAccessTest.php src/Service/NotificationService.php src/Controller/EmployeeController.php src/Form/EmployeeTaskFormType.php templates/employee/dashboard.html.twig templates/employee/project_show.html.twig templates/task/show.html.twig
git commit -m "feat: support multiple task assignees"
```

### Task 7: Status History, Recurrence Hooks, and Activity Coverage

**Files:**
- Create: `tests/Controller/TaskLifecycleTest.php`
- Modify: `src/Controller/AdminController.php`
- Modify: `src/Controller/AdminTaskController.php`
- Modify: `src/Controller/EmployeeController.php`

**Interfaces:**
- Every status-changing controller calls a shared private/service lifecycle method with previous status, actor, recurrence service, and activity service.
- Newly completed recurring tasks persist one next occurrence in the same transaction.

- [ ] **Step 1: Write failing lifecycle integration tests**

Test admin and employee status changes, history actor/timestamps, activity summaries, one recurrence on first completion, and no duplicate on repeated completion submissions.

- [ ] **Step 2: Verify RED**

Run: `php bin/phpunit tests/Controller/TaskLifecycleTest.php`

Expected: FAIL because lifecycle services are not wired into mutations.

- [ ] **Step 3: Wire lifecycle services into all mutations**

Record create/edit/assignment/comment/document/dependency/time/problem events. Store concise human-readable summaries and structured changed-field metadata without sensitive manager-note content.

- [ ] **Step 4: Verify GREEN**

Run: `php bin/phpunit tests/Controller/TaskLifecycleTest.php`

Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add tests/Controller/TaskLifecycleTest.php src/Controller/AdminController.php src/Controller/AdminTaskController.php src/Controller/EmployeeController.php
git commit -m "feat: record task lifecycle and recurrence"
```

### Task 8: Workspace Styling and Progressive Enhancement

**Files:**
- Modify: `public/styles.css`
- Modify: `templates/base.html.twig`
- Modify: `templates/admin/tasks.html.twig`
- Modify: `templates/admin/_task_detail_panel.html.twig`

**Interfaces:**
- DOM hooks: `[data-task-toggle]`, `[data-task-parent]`, `[data-task-dialog]`, `[data-task-dialog-close]`, `[data-task-tabs]`.
- Session key: `waldbyte.taskWorkspace.expanded.<projectId>` containing a JSON list of expanded task IDs.

- [ ] **Step 1: Add rendering assertions for accessible hooks**

Extend `AdminTaskWorkspaceTest` to assert `role="treegrid"`, `aria-level`, `aria-expanded`, dialog labelling, close link fallback, focusable task links, and non-color status text.

- [ ] **Step 2: Verify RED**

Run: `php bin/phpunit tests/Controller/AdminTaskWorkspaceTest.php`

Expected: FAIL for missing accessibility hooks.

- [ ] **Step 3: Implement CSS and JavaScript behavior**

Add desktop two-column layout, sticky project card, responsive selector, indented tree rows, avatar stacks, status chips, full-screen mobile dialog, dark-mode variables, focus styles, overflow handling, and reduced-motion handling. JavaScript restores expansion from session storage, toggles descendants, manages panel tabs, traps focus, closes on Escape/backdrop, and restores origin focus.

- [ ] **Step 4: Verify GREEN and asset syntax**

Run: `php bin/phpunit tests/Controller/AdminTaskWorkspaceTest.php && php bin/console lint:twig templates && php bin/console lint:container`

Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add public/styles.css templates/base.html.twig templates/admin/tasks.html.twig templates/admin/_task_detail_panel.html.twig tests/Controller/AdminTaskWorkspaceTest.php
git commit -m "feat: polish task workspace interactions"
```

### Task 9: Final Migration and Regression Verification

**Files:**
- Modify only files required by failures found below.

**Interfaces:**
- Produces a deployable schema migration chain and regression-clean application.

- [ ] **Step 1: Run the complete automated suite**

Run: `php bin/phpunit`

Expected: all tests pass with no warnings or deprecations introduced by this feature.

- [ ] **Step 2: Run static framework checks**

Run: `php bin/console lint:container && php bin/console lint:twig templates && php bin/console doctrine:schema:validate --skip-sync && php bin/console doctrine:migrations:up-to-date`

Expected: all commands succeed.

- [ ] **Step 3: Exercise migrations on an empty test database**

Run in the configured isolated test environment: `APP_ENV=test php bin/console doctrine:database:create --if-not-exists && APP_ENV=test php bin/console doctrine:migrations:migrate --no-interaction`

Expected: migrations complete successfully.

- [ ] **Step 4: Manual UI verification**

Open `/admin/tasks` and verify project switching, three-level expansion, create/edit/delete, multiple assignees, every detail section, deep-link panel URLs, timer continuity, upload/download, keyboard operation, mobile width, and both themes. Verify an employee sees and can update any assigned task but never sees manager notes.

- [ ] **Step 5: Review scoped diff**

Run: `git status --short && git diff --check && git log --oneline --max-count=12`

Expected: only planned feature files plus the user's pre-existing `.idea` and document-template changes appear; no whitespace errors.

- [ ] **Step 6: Commit verification fixes if needed**

If verification changes `src/Controller/AdminTaskController.php` and `public/styles.css`, for example, stage those exact files and commit them; never stage the user's pre-existing `.idea` or `templates/documents/index.html.twig` changes.

```bash
git add src/Controller/AdminTaskController.php public/styles.css
git commit -m "fix: complete task workspace verification"
```
