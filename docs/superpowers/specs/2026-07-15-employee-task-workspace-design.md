# Employee Task Workspace Design

## Goal

Replace the employee Tasks tab's mixed create form and card list with the same project-centered workspace used by administrators. Employees should navigate projects, expand recursive task hierarchies, open task details without leaving the dashboard, and control their own task timer from either the list or detail panel.

## Selected Approach

Use full workspace parity with the admin task page while preserving employee authorization. This was selected over:

1. Restyling the existing employee cards, which would retain the current mixed layout and would not provide project navigation or recursive hierarchy.
2. Adding a separate employee task page, which would duplicate dashboard navigation and split the employee experience across two locations.

The employee Tasks tab remains part of the dashboard and receives the established workspace layout and interactions.

## Workspace Layout

The Tasks tab contains:

- A compact project sidebar listing projects available to the employee.
- A task tree for the selected project, including unlimited expandable subtask levels.
- A compact “Add Task” action that opens task creation instead of permanently occupying most of the page.
- Clickable task rows. Clicking non-interactive row space opens the detail dialog; expand, timer, status, and other controls keep their own behavior.
- Responsive behavior matching the admin workspace, including horizontal table scrolling and stacked project navigation on narrow screens.

The selected project is stored in the URL query string so reloads and shared links retain context. Task detail links include both the project and task IDs.

## Visibility and Permissions

Employees see the same project workspace structure as administrators for projects to which they belong. Within a selected project, all project tasks appear so the hierarchy remains complete and understandable.

Authorization remains role-specific:

- Any project member may view project task rows and non-sensitive task details.
- Assignees may update task status and start or stop their own timer.
- Assignees and the task creator may comment. Other project members have read-only task context.
- Employees may create tasks in their projects and choose active employee assignees, matching current behavior.
- Employees cannot edit admin-owned configuration, delete tasks, manage billing, see manager notes, manage dependencies, resolve problems, or administer other employees' time.
- Direct routes enforce the same authorization as the interface; hidden controls are not the security boundary.

## Task Creation and Subtasks

The large inline create form becomes a dialog or compact expandable panel launched by “Add Task.” It preserves the existing fields and validation.

When launched from a task row or the Subtasks tab, the form creates a child of that task. Parent selection is limited to the same project, and the existing hierarchy service rejects cycles and cross-project parents. Subtasks may have further subtasks without a depth limit.

## Task Tree

The tree uses the shared hierarchy service and the same columns and visual language as the admin workspace:

- Task name and hierarchy depth
- Assignees
- Status and completion
- Priority
- Tags
- Schedule
- Tracked and estimated time
- Contextual actions

Employee-specific row controls are:

- Play icon for an assigned, non-completed task when its timer is inactive.
- Stop icon for the employee's active task.
- Add-subtask icon for projects where the employee has access.
- Status control only when assigned.

Only one timer may run per employee. Starting another task closes the previous timer and pauses its task. Timer controls continue to require an active attendance session and no active break, preserving current employee rules.

## Detail Dialog

Opening a row displays an employee version of the task detail dialog. It uses the existing admin visual structure but filters capabilities:

- Header with task identity, project, close action, and assigned-user timer icon.
- Summary with status, priority, completion, assignees, tags, schedule, tracked time, reminder, recurrence, and billing label where non-sensitive.
- Description.
- Comments tab. Assignees and the task creator may post comments and edit or delete only their own comments; other project members may read them.
- Subtasks tab with recursive navigation and add-subtask action.
- Time Logs tab showing only the signed-in employee's entries for that task, without manual admin time entry.
- Documents tab listing task attachments with read-only download access; upload and delete remain administrative.
- Status Timeline and Activity tabs as read-only task history.
- Problems tab. Assignees and the task creator may report blockers; administrative resolution remains unavailable.

Manager notes are never rendered in employee HTML.

## Data and Controller Flow

The employee dashboard controller reads the selected project and task from query parameters, validates project membership, fetches the selected project's complete task set, builds the hierarchy, and passes the employee's active timer entry to the templates.

Existing employee timer, status, comment, attendance, and notification flows remain the source of truth. Redirect helpers preserve tab=tasks, selected project, and selected task so form submissions return to the same workspace context.

Employee task creation accepts an optional parent ID, validates membership and same-project ancestry, then creates the task with the current employee as creator and the selected assignees.

## Error Handling

- Invalid or inaccessible project IDs fall back to the employee's first available project.
- Inaccessible task IDs are rejected or ignored without leaking task details.
- Timer failures use the current flash messages for missing check-in or active breaks and return to the selected workspace.
- Invalid parent relationships produce a clear flash error and do not persist the task.
- Empty projects show a focused empty state with an Add Task action.

## Testing

Automated coverage will verify:

- Project membership limits the sidebar and workspace.
- The complete hierarchy is built for a member's selected project.
- Direct task detail access never exposes manager notes.
- Assignees receive status and timer controls; non-assignees do not.
- Timer start and stop preserve the selected workspace and existing attendance requirements.
- Child and deeper subtask creation validates project and parent rules.
- Clickable rows ignore nested interactive controls and support keyboard activation.
- Templates contain the project navigator, treegrid, detail dialog, and responsive hooks.

Framework syntax, dependency injection, routes, the full PHPUnit suite, and authenticated employee rendering will be verified before completion.
