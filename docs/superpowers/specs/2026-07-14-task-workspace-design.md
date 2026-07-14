# Project Task Workspace Design

## Purpose

Replace the mixed admin task table with a project-focused workspace. Administrators can select a project, navigate an unlimited-depth task hierarchy, and manage a task without losing the surrounding list context. Tasks and subtasks share the same capabilities.

## Scope

The workspace includes:

- a compact project navigator;
- a recursively expandable task table;
- full tasks at every hierarchy level;
- multiple assignees;
- tags and scheduling fields;
- an in-page task detail panel;
- reminders and recurrence;
- billing metadata and a manager note;
- comments, time logs, documents, dependencies, problems, status history, and activity history;
- compatible employee visibility, permissions, timers, and notifications.

The interface will follow Waldbyte HR's existing colors, typography, cards, dark mode, and responsive patterns. The supplied screenshots are interaction references, not visual templates.

## Task Workspace

The admin Tasks route renders a two-column workspace.

### Project navigator

The left column is a narrow card listing all projects, ordered with active projects first and then by name. Each item shows the project name, status, and total task count. The selected project has a clear active state.

The project ID is stored in the `project` query parameter. On a first visit without a valid project ID, the first active project is selected; if no active project exists, the first available project is selected. When there are no projects, the workspace shows an empty state with an Add Project action.

Changing projects performs a normal GET request. The URL is therefore bookmarkable and browser navigation works without custom state restoration.

### Task tree

Only tasks belonging to the selected project appear in the main column. Top-level tasks are initially visible. A task with children has an expand/collapse control that reveals its direct children; each child uses the same behavior recursively. The browser remembers expanded task IDs for the current page session.

Each row shows:

- a stable display ID based on the database task ID;
- task title and hierarchy indentation;
- assignee avatars or initials, with an overflow count;
- status;
- priority;
- tags;
- start date and due date;
- estimated and tracked time;
- child completion summary;
- View, Add Subtask, Edit, and Delete actions as permitted.

An Add Task action creates a top-level task for the selected project. Add Subtask creates a task whose parent is the selected row. The project is inherited from the parent and cannot differ. Editing a task cannot move it beneath itself or one of its descendants.

Deleting a task requires confirmation and deletes its descendant subtree through the database relationship. The confirmation explicitly says that descendants, comments, time entries, documents, and history will also be removed.

## Task Detail Panel

Selecting a row opens a large, accessible modal panel over the workspace. The workspace remains visible behind it. The selected task is represented by a `task` query parameter so direct links, refreshes, and notification links reopen the same task in context. Closing the panel removes only that parameter.

The panel header contains the task type, display ID, title, creator, project, assignees, status, and close control. It supports keyboard focus trapping, Escape to close, and focus restoration to the originating row.

The summary area exposes:

- status and priority;
- completion percentage;
- multiple assignees;
- tags;
- start and due dates;
- estimated time and tracked time;
- reminder date/time;
- recurrence rule;
- billing type;
- manager note;
- description.

The panel sections are:

1. **Comments** — existing comment creation, editing, deletion, and notifications.
2. **Subtasks** — the selected task's direct children with navigation and Add Subtask.
3. **Time Logs** — existing timer entries plus manual time-entry creation for administrators.
4. **Documents** — upload, list, download, and delete task attachments.
5. **Dependencies** — add or remove project-local task prerequisites and show whether each prerequisite is complete.
6. **Status Timeline** — immutable status-change entries with actor and timestamp.
7. **Problems** — add and resolve task-specific problem records.
8. **Activity** — immutable audit entries for task creation, important field changes, assignment, comments, documents, dependencies, time logs, and problems.

The initial open section is Comments. Section selection is client-side and does not discard unsaved forms without confirmation.

## Task Model

### Hierarchy

`Task` gains a nullable self-referencing `parent` and an ordered `children` collection. A task may have unlimited descendants. Application validation enforces:

- a child and parent belong to the same project;
- a task cannot parent itself;
- a task cannot be moved beneath any descendant;
- hierarchy traversal is cycle-safe even if invalid legacy data is encountered.

Queries fetch the selected project's tasks in a bounded number of database calls, assemble the tree in application code, and avoid recursive per-row queries.

### Assignment

The single `assignedTo` relation becomes a many-to-many `assignees` collection. The migration copies every current assignee into the join table before the old foreign key is removed. Existing employee access rules treat any collection member as an assignee. Creating a task as an employee adds that employee to the collection by default.

Notifications go to all assignees and the creator, excluding the actor and duplicate recipients. Newly added assignees receive an assignment notification. Removing one assignee does not revoke project membership automatically.

### Core fields

`Task` gains:

- `startDate`: nullable date;
- `dueDate`: nullable date;
- `tags`: normalized JSON list of unique trimmed labels;
- `reminderAt`: nullable immutable datetime;
- `recurrence`: nullable constrained string (`daily`, `weekly`, or `monthly`);
- `billingType`: constrained string (`billable` or `non_billable`), default `billable`;
- `managerNote`: nullable text.

Due date cannot precede start date. Reminder time is stored in UTC and rendered in the application timezone. Tags are case-insensitively de-duplicated while preserving the first label's casing.

### Completion

A task's own status remains independently editable. Its displayed completion percentage is calculated from the statuses of all leaf descendants. A task with no descendants displays 100% when completed and 0% otherwise. A parent with descendants displays the percentage of completed leaf tasks; parent status is not changed automatically.

### Recurrence

When a recurring task is changed from a non-completed status to completed, one next occurrence is created. It copies the task's project, parent, title, description, assignees, priority, estimate, tags, recurrence, billing type, and manager note. Dates and reminder advance by the recurrence interval. Comments, time logs, documents, dependencies, problems, and children are not copied. A source-occurrence link and uniqueness constraint prevent duplicate generation.

### Supporting records

Dedicated entities keep task behavior isolated:

- `TaskDocument`: task, uploader, original name, stored name, MIME type, size, created time;
- `TaskDependency`: task and prerequisite task, unique as a pair;
- `TaskProblem`: task, author, description, resolution status, resolver, created and resolved times;
- `TaskStatusHistory`: task, actor, previous status, new status, created time;
- `TaskActivity`: task, actor, event type, human-readable summary, structured metadata, created time.

Dependency validation requires both tasks to share a project, rejects self-dependencies, and rejects dependency cycles.

Uploaded files use random stored names outside the public directory and are served through an authorized download route. File type and size limits follow the application's document-upload security pattern.

## Forms and Mutations

The existing task form becomes the canonical full-task form and supports parent context, multiple assignees, dates, tags, reminder, recurrence, billing type, and manager note. A compact create form in the workspace captures title and optional assignees; the full panel can edit all fields afterward.

Every state-changing route uses POST, Symfony CSRF protection, validation, and a redirect back to the selected project/task context. Invalid requests show a clear flash error and preserve as much form input as Symfony forms allow.

## Employee Experience and Authorization

Administrators retain full access. Employees can see a task when they created it or are one of its assignees, subject to existing project membership rules. A visible descendant does not automatically expose a hidden parent's private detail; tree lists may show a minimal ancestor breadcrumb needed for context.

Any assignee can update status, add comments, and use timers under the current employee rules. Only administrators can edit task configuration, assignees, billing metadata, manager notes, dependencies, or delete records. Manager notes are never rendered in employee views.

Employee dashboard and project task cards are updated to show multiple assignees and link to the enhanced task view. Notification URLs open the admin workspace panel for administrators and the employee task view for employees.

## Responsiveness and Accessibility

At desktop widths the project navigator and task table are side by side. On narrow screens the project navigator becomes a full-width selector above a horizontally scrollable task list. The detail panel fills the viewport on small screens.

Controls have visible focus states and text alternatives. Tree rows expose `aria-expanded`, nesting uses `aria-level`, status is not communicated by color alone, and avatars have accessible names. All actions remain usable without JavaScript through normal links and forms; JavaScript enhances expansion and modal behavior.

## Testing

Automated coverage will include:

- hierarchy creation, recursive tree assembly, project consistency, and cycle prevention;
- assignment migration behavior, employee visibility, permissions, and notification recipients;
- completion rollups across multiple depths;
- date, tag, reminder, recurrence, and dependency validation;
- single next-occurrence generation;
- controller filtering by project and preservation of query context;
- document authorization and deletion;
- status history and activity creation;
- template rendering for project navigation, nested rows, and the detail panel.

Manual verification will cover desktop and mobile layouts, light and dark themes, keyboard navigation, deeply nested expansion, task-panel open/close behavior, timers, uploads, and empty states.

## Migration and Compatibility

Database migrations are additive until assignment data has been copied successfully. New nullable fields and supporting tables are created first, assignee rows are backfilled second, and the old assignment relation is removed last. Existing tasks become top-level tasks. Existing comments and time entries remain attached to their tasks.

No unrelated project, attendance, leave, directory, or employee-document behavior is changed.
