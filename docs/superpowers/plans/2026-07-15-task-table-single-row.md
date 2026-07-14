# Task Table Single-Row Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox syntax for tracking.

**Goal:** Show every admin and employee task-table column within one desktop row without horizontal scrolling.

**Architecture:** Add identical colgroups to the admin and employee task tables so shared CSS can assign stable proportions. Break only the task workspace out to a centered desktop width of up to 1760px, use fixed table layout on desktop, and restore the current minimum-width scrolling behavior below 1200px.

**Tech Stack:** Twig, CSS, PHPUnit 13, Symfony 7.4.

## Global Constraints

- Apply the layout to both admin and employee task workspaces.
- Keep all eight columns visible on desktop.
- Keep existing controls and click behavior unchanged.
- Preserve horizontal scrolling below 1200px.
- Do not change routes, permissions, task data, or database structures.

---

### Task 1: Shared fixed-column desktop layout

**Files:**
- Modify: templates/admin/tasks.html.twig
- Modify: templates/employee/_task_workspace.html.twig
- Modify: public/styles.css
- Modify: templates/base.html.twig
- Modify: tests/Controller/AdminTaskWorkspaceTest.php
- Modify: tests/Controller/EmployeeTaskWorkspaceTest.php

**Interfaces:**
- Consumes the existing task-workspace, task-tree, and task-name-cell classes.
- Produces task-workspace--wide, eight task-col classes, fixed desktop column proportions, and a below-1200px scrolling fallback.

- [ ] **Step 1: Write failing markup and CSS assertions**

Add these expectations to both workspace test classes:

    self::assertStringContainsString('task-workspace--wide', $template);
    self::assertSame(8, substr_count($template, 'class="task-col task-col--'));

Add these CSS expectations to AdminTaskWorkspaceTest:

    self::assertStringContainsString('table-layout: fixed', $css);
    self::assertStringContainsString('.task-col--name', $css);
    self::assertStringContainsString('@media (max-width: 1199px)', $css);
    self::assertStringContainsString('min-width: 1120px', $css);

- [ ] **Step 2: Run tests and verify RED**

Run:

    php bin/phpunit tests/Controller/AdminTaskWorkspaceTest.php tests/Controller/EmployeeTaskWorkspaceTest.php

Expected: FAIL because the breakout class, colgroups, and fixed desktop CSS do not exist.

- [ ] **Step 3: Add matching table colgroups**

Add task-workspace--wide to both workspace containers. Immediately inside each task table, add:

    <colgroup>
        <col class="task-col task-col--name">
        <col class="task-col task-col--assignees">
        <col class="task-col task-col--status">
        <col class="task-col task-col--priority">
        <col class="task-col task-col--tags">
        <col class="task-col task-col--schedule">
        <col class="task-col task-col--time">
        <col class="task-col task-col--actions">
    </colgroup>

- [ ] **Step 4: Implement desktop breakout and proportions**

Replace the unconditional task-table minimum with:

    .task-workspace--wide {
        left: 50%;
        max-width: 1760px;
        position: relative;
        transform: translateX(-50%);
        width: calc(100vw - 56px);
    }

    .task-tree {
        table-layout: fixed;
        width: 100%;
    }

Assign widths of 25%, 10%, 14%, 8%, 9%, 13%, 13%, and 8% to the eight task-col classes. Set the task-name cell minimum to zero, truncate the link text with ellipsis, use overflow-wrap for other cells, and reduce desktop cell padding to 10px.

- [ ] **Step 5: Restore smaller-screen scrolling**

Add:

    @media (max-width: 1199px) {
        .task-workspace--wide {
            left: auto;
            max-width: none;
            transform: none;
            width: 100%;
        }

        .task-tree {
            min-width: 1120px;
            table-layout: auto;
        }

        .task-name-cell {
            min-width: 250px;
        }
    }

Leave the existing 820px project-sidebar stacking behavior unchanged.

- [ ] **Step 6: Bump the stylesheet cache key**

Change waldbyte-hr-20 to waldbyte-hr-21 in templates/base.html.twig and update the existing cache-key assertion.

- [ ] **Step 7: Run verification**

    php bin/phpunit
    php bin/console lint:twig templates/admin/tasks.html.twig templates/employee/_task_workspace.html.twig templates/base.html.twig
    git diff --check

Expected: all commands exit 0.

- [ ] **Step 8: Rebuild and verify rendered HTML**

Run docker compose up -d --build app, authenticate to both task workspaces, and confirm both rendered tables contain task-workspace--wide and all eight task-col elements.

- [ ] **Step 9: Commit**

    git add templates/admin/tasks.html.twig templates/employee/_task_workspace.html.twig templates/base.html.twig public/styles.css tests/Controller/AdminTaskWorkspaceTest.php tests/Controller/EmployeeTaskWorkspaceTest.php
    git commit -m "fix: fit task tables on one desktop row"
