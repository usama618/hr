# Employee Task Workspace Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox syntax for tracking.

**Goal:** Replace the employee Tasks tab's large create form and task cards with the admin-style project navigator, recursive task tree, detail dialog, subtasks, and employee timer controls.

**Architecture:** Extend EmployeeController::dashboard() to resolve a selected employee project and task, fetch the complete project hierarchy, and expose permission flags without weakening direct-route checks. Keep the dashboard as the entry point, move the new UI into employee-specific Twig partials, and reuse the existing task workspace CSS and JavaScript hooks. Existing employee status, timer, comment, attendance, lifecycle, and notification flows remain authoritative.

**Tech Stack:** PHP 8.4, Symfony 7.4, Doctrine ORM, Twig, vanilla JavaScript, CSS, PHPUnit 13, Docker Compose.

## Global Constraints

- Employees only see projects to which they belong.
- All tasks in a selected employee project appear to preserve the hierarchy.
- Only assignees may update status or control timers.
- Timer start still requires an active attendance session and no active break.
- Assignees and task creators may comment and report problems.
- Manager notes never appear in employee-rendered HTML.
- Employee document access is read-only.
- Subtasks may nest without a depth limit and must remain in the same project.
- No schema migration or new dependency is required.

---

### Task 1: Employee workspace query model

**Files:**
- Modify: src/Controller/EmployeeController.php
- Modify: src/Repository/TaskRepository.php
- Create: tests/Controller/EmployeeTaskWorkspaceTest.php

**Interfaces:**
- Consumes: TaskRepository::findWorkspaceTasks(Project $project): array and TaskHierarchyService::buildTree(iterable $tasks): array.
- Produces dashboard variables task_projects, selected_task_project, employee_task_rows, selected_employee_task, and active_task_entry.

- [ ] **Step 1: Write the failing workspace-data test**

Create the test with:

    public function testDashboardBuildsAProjectScopedTaskWorkspace(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2).'/src/Controller/EmployeeController.php');
        self::assertIsString($source);
        foreach (['TaskRepository', 'TaskHierarchyService', "'task_projects'", "'selected_task_project'", "'employee_task_rows'", "'selected_employee_task'"] as $needle) {
            self::assertStringContainsString($needle, $source);
        }
        self::assertStringContainsString("query->get('project'", $source);
        self::assertStringContainsString("query->get('task'", $source);
        self::assertStringContainsString('getProjects()->contains', $source);
    }

- [ ] **Step 2: Run the test and verify RED**

Run: php bin/phpunit tests/Controller/EmployeeTaskWorkspaceTest.php

Expected: FAIL because the workspace dependencies and variables are absent.

- [ ] **Step 3: Implement project and task selection**

Inject TaskRepository $tasks and TaskHierarchyService $hierarchy into dashboard(). Resolve the selected project only from the employee's project collection, fall back to the first project, fetch all project tasks, and select a task only from that result.

    $taskProjects = array_values($employee->getProjects()->toArray());
    $selectedTaskProject = null;
    $projectId = (int) $request->query->get('project', 0);
    foreach ($taskProjects as $project) {
        if ($project->getId() === $projectId) {
            $selectedTaskProject = $project;
            break;
        }
    }
    $selectedTaskProject ??= $taskProjects[0] ?? null;
    $workspaceTasks = $selectedTaskProject ? $tasks->findWorkspaceTasks($selectedTaskProject) : [];

Pass the five named workspace variables to employee/dashboard.html.twig.

- [ ] **Step 4: Run tests and verify GREEN**

Run: php bin/phpunit tests/Controller/EmployeeTaskWorkspaceTest.php tests/Service/TaskHierarchyServiceTest.php

Expected: PASS.

- [ ] **Step 5: Commit**

    git add src/Controller/EmployeeController.php src/Repository/TaskRepository.php tests/Controller/EmployeeTaskWorkspaceTest.php
    git commit -m "feat: build employee task workspace data"

---

### Task 2: Employee project navigator and recursive task tree

**Files:**
- Create: templates/employee/_task_workspace.html.twig
- Create: templates/employee/_task_tree_rows.html.twig
- Modify: templates/employee/dashboard.html.twig
- Modify: templates/admin/tasks.html.twig
- Modify: templates/base.html.twig
- Modify: public/styles.css
- Modify: tests/Controller/EmployeeTaskWorkspaceTest.php
- Modify: tests/Controller/AdminTaskWorkspaceTest.php

**Interfaces:**
- Consumes Task 1 workspace variables and routes employee_task_start, employee_task_pause, and employee_task_status.
- Produces DOM hooks data-task-workspace, data-task-row-url, data-task-toggle, and accessible task timer forms.

- [ ] **Step 1: Write failing template assertions**

Read the dashboard plus new partial paths and assert:

    foreach (['task-project-nav', 'role="treegrid"', 'data-task-row-url', 'data-task-toggle', 'task-timer-control', 'employee_task_start', 'employee_task_pause'] as $needle) {
        self::assertStringContainsString($needle, $templates);
    }

- [ ] **Step 2: Run the test and verify RED**

Run: php bin/phpunit tests/Controller/EmployeeTaskWorkspaceTest.php

Expected: FAIL because the employee workspace partials do not exist.

- [ ] **Step 3: Replace the task-card loop**

Replace only the employee Tasks tab body with an include of employee/_task_workspace.html.twig. Pass employee, task_projects, selected_task_project, employee_task_rows, selected_employee_task, active_task_entry, task_status_options, and task_form.

- [ ] **Step 4: Build the employee task rows**

Render all project tasks. Derive is_assigned_to_me and is_active_timer for every row. Show status and timer forms only to assignees. Use the same hierarchy indentation, assignee stack, badges, tags, schedule, live duration, play icon, and stop icon as admin.

The row URL is generated with:

    path('employee_dashboard', {tab: 'tasks', project: selected_task_project.id, task: task.id})

Timer forms send return_to=workspace_list.

- [ ] **Step 5: Namespace row expansion state**

Add data-task-workspace-scope="employee" on the employee workspace and "admin" on the admin workspace. Update the base JavaScript storage key to include that scope, while retaining the existing control-click exclusion and keyboard behavior.

- [ ] **Step 6: Add minimal employee-specific CSS**

Reuse existing task workspace classes. Add selectors only for dashboard containment and the compact employee status select. Bump the stylesheet cache key from waldbyte-hr-19 to waldbyte-hr-20.

- [ ] **Step 7: Run tests and lint**

    php bin/phpunit tests/Controller/EmployeeTaskWorkspaceTest.php tests/Controller/AdminTaskWorkspaceTest.php
    php bin/console lint:twig templates/employee/dashboard.html.twig templates/employee/_task_workspace.html.twig templates/employee/_task_tree_rows.html.twig templates/admin/tasks.html.twig templates/base.html.twig

Expected: PASS and Twig lint OK.

- [ ] **Step 8: Commit**

    git add templates/employee templates/admin/tasks.html.twig templates/base.html.twig public/styles.css tests/Controller/EmployeeTaskWorkspaceTest.php tests/Controller/AdminTaskWorkspaceTest.php
    git commit -m "feat: add employee project task tree"

---

### Task 3: Employee task creation and recursive subtasks

**Files:**
- Create: templates/employee/_task_create_dialog.html.twig
- Modify: src/Controller/EmployeeController.php
- Modify: templates/employee/_task_workspace.html.twig
- Modify: templates/employee/_task_tree_rows.html.twig
- Modify: tests/Controller/EmployeeTaskWorkspaceTest.php

**Interfaces:**
- Consumes query parameters create=1, project, and optional parent; consumes TaskHierarchyService::assertValidParent().
- Produces POST field parent_id and project-preserving redirects.

- [ ] **Step 1: Write failing subtask assertions**

    self::assertStringContainsString("request->request->get('parent_id'", $controller);
    self::assertStringContainsString('assertValidParent', $controller);
    self::assertStringContainsString('parent: task.id', $rows);

- [ ] **Step 2: Run the test and verify RED**

Run: php bin/phpunit tests/Controller/EmployeeTaskWorkspaceTest.php

Expected: FAIL because employee creation has no parent support.

- [ ] **Step 3: Resolve and validate the parent**

Inject TaskHierarchyService into createTask(). Load parent_id, require the parent to belong to the submitted project, require employee project membership, call setParent() and assertValidParent(), and catch DomainException without persisting.

- [ ] **Step 4: Build the creation dialog**

Render the existing employee task form in employee/_task_create_dialog.html.twig only when create=1. Include a hidden parent_id, show Add Task or Add Subtask in the title, and keep tab=tasks plus project in open and close URLs.

- [ ] **Step 5: Preserve context after creation**

On success redirect to employee_dashboard with tab=tasks, project equal to the created task's project ID, and task equal to its ID. On failure return to the same create dialog.

- [ ] **Step 6: Run tests and lint**

    php bin/phpunit tests/Controller/EmployeeTaskWorkspaceTest.php tests/Service/TaskHierarchyServiceTest.php
    php bin/console lint:twig templates/employee/_task_create_dialog.html.twig templates/employee/_task_workspace.html.twig templates/employee/_task_tree_rows.html.twig

Expected: PASS and Twig lint OK.

- [ ] **Step 7: Commit**

    git add src/Controller/EmployeeController.php templates/employee tests/Controller/EmployeeTaskWorkspaceTest.php
    git commit -m "feat: let employees create recursive subtasks"

---

### Task 4: Employee task detail dialog and support actions

**Files:**
- Create: templates/employee/_task_detail_panel.html.twig
- Modify: src/Controller/EmployeeController.php
- Modify: templates/employee/_task_workspace.html.twig
- Modify: tests/Controller/EmployeeTaskWorkspaceTest.php
- Modify: tests/Controller/EmployeeTaskAccessTest.php

**Interfaces:**
- Consumes selected_employee_task, current employee, active timer, task collections, and task documents under var/task-documents.
- Produces routes employee_task_document_download and employee_task_problem_add.

- [ ] **Step 1: Write failing detail and security assertions**

Assert the panel has Comments, Subtasks, Time Logs, Documents, Status Timeline, Problems, and Activity. Assert it excludes managerNote and Add Time Log. Assert the controller contains both new routes and participant guards.

- [ ] **Step 2: Run tests and verify RED**

Run: php bin/phpunit tests/Controller/EmployeeTaskWorkspaceTest.php tests/Controller/EmployeeTaskAccessTest.php

Expected: FAIL because the panel and routes do not exist.

- [ ] **Step 3: Redirect legacy detail URLs**

Change showTask() to require project membership and redirect to employee_dashboard with tab=tasks, project, and task. Change denyUnlessTaskVisible() to project membership for non-sensitive details. Add denyUnlessTaskParticipant() for assignee-or-creator comment and problem mutations.

- [ ] **Step 4: Build the detail panel**

Mirror the admin dialog structure but render:

- Timer and status controls only for assignees.
- Summary without manager notes.
- Comment posting only for participants and edit/delete only for the author.
- Recursive subtasks and Add Subtask.
- Only the signed-in employee's time entries.
- Read-only document download links.
- Read-only status history and activity.
- Problem reporting for participants, with resolution unavailable.

- [ ] **Step 5: Add guarded support routes**

Add a GET document route that resolves the task, verifies project membership and the stored file, and returns BinaryFileResponse. Add a POST problem route that requires participant access, rejects an empty description, persists TaskProblem, records activity, and redirects via employee_task_show.

- [ ] **Step 6: Run tests and lint**

    php bin/phpunit tests/Controller/EmployeeTaskWorkspaceTest.php tests/Controller/EmployeeTaskAccessTest.php
    php bin/console lint:twig templates/employee/_task_detail_panel.html.twig templates/employee/_task_workspace.html.twig
    php bin/console lint:container

Expected: PASS, Twig lint OK, and container lint OK.

- [ ] **Step 7: Commit**

    git add src/Controller/EmployeeController.php templates/employee tests/Controller/EmployeeTaskWorkspaceTest.php tests/Controller/EmployeeTaskAccessTest.php
    git commit -m "feat: add employee task detail workspace"

---

### Task 5: Preserve workspace context through actions

**Files:**
- Modify: src/Controller/EmployeeController.php
- Modify: templates/employee/_task_tree_rows.html.twig
- Modify: templates/employee/_task_detail_panel.html.twig
- Modify: tests/Controller/EmployeeTaskWorkspaceTest.php

**Interfaces:**
- Consumes return_to values workspace_list and workspace_task.
- Produces redirectToTaskWorkspace(Task $task, bool $keepTask): Response.

- [ ] **Step 1: Write failing redirect assertions**

    self::assertStringContainsString("'workspace_list'", $controller);
    self::assertStringContainsString("'workspace_task'", $controller);
    self::assertStringContainsString('redirectToTaskWorkspace', $controller);

- [ ] **Step 2: Run the test and verify RED**

Run: php bin/phpunit tests/Controller/EmployeeTaskWorkspaceTest.php

Expected: FAIL because timers redirect to the dashboard root.

- [ ] **Step 3: Add the redirect helper**

    private function redirectToTaskWorkspace(Task $task, bool $keepTask): Response
    {
        return $this->redirectToRoute('employee_dashboard', [
            'tab' => 'tasks',
            'project' => $task->getProject()?->getId(),
            'task' => $keepTask ? $task->getId() : null,
        ]);
    }

Use it from timer start and pause error/success paths. Extend redirectAfterTaskStatusUpdate() for workspace_list and workspace_task while keeping legacy returns.

- [ ] **Step 4: Add return fields**

Rows send workspace_list and the detail dialog sends workspace_task. Comments and problems redirect through employee_task_show, which now lands in the workspace.

- [ ] **Step 5: Run tests**

Run: php bin/phpunit tests/Controller/EmployeeTaskWorkspaceTest.php tests/Controller/EmployeeTaskAccessTest.php

Expected: PASS.

- [ ] **Step 6: Commit**

    git add src/Controller/EmployeeController.php templates/employee/_task_tree_rows.html.twig templates/employee/_task_detail_panel.html.twig tests/Controller/EmployeeTaskWorkspaceTest.php
    git commit -m "fix: preserve employee task workspace context"

---

### Task 6: Full verification and live employee check

**Files:**
- Verify all changed files.

**Interfaces:**
- Consumes the complete employee workspace.
- Produces verified application behavior with no timer left open by testing.

- [ ] **Step 1: Run automated verification**

    php bin/phpunit
    php -l src/Controller/EmployeeController.php
    php bin/console lint:twig templates
    php bin/console lint:container
    php bin/console doctrine:schema:validate
    composer validate --strict
    git diff --check

Expected: every command exits 0.

- [ ] **Step 2: Rebuild the application**

Run: docker compose up -d --build app

Expected: application starts and the database stays healthy.

- [ ] **Step 3: Verify employee rendering**

Sign in as an employee and request /employee?tab=tasks plus a project-and-task deep link. Verify HTTP 200, project sidebar, tree, detail dialog, recursive rows, and no manager note or admin-only controls.

- [ ] **Step 4: Verify timer transitions**

With the employee checked in and not on break, start a timer from the list, verify stop icons and live duration in list and detail, stop it, verify play icons return, and confirm no timer remains open.

- [ ] **Step 5: Verify responsive themes**

Check desktop and narrow widths in both themes. Confirm the sidebar stacks, the task tree scrolls, dialogs remain usable, and all controls are reachable.

- [ ] **Step 6: Review the final diff**

Confirm the final diff contains only the employee workspace implementation. If verification exposed a defect, correct it, rerun Step 1, and commit the verified correction before handoff.
