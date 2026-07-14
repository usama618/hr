# Task Table Single-Row Desktop Design

## Goal

Remove unnecessary horizontal scrolling from the admin and employee task workspaces on desktop. Every task column and its action controls must remain visible within one task row.

## Root Cause

The application page is capped at 1220px. The task workspace then reserves up to 255px for the project sidebar, leaving roughly 920px for the task panel. The task table has a hard minimum width of 1120px and the task-name cell has a 250px minimum, so the table must overflow even on a wide monitor.

## Selected Layout

Both task workspaces will use a centered desktop breakout width of up to 1760px, constrained to the viewport with safe side margins. Other dashboard tabs and application pages retain the existing 1220px page width.

At desktop widths:

- The project sidebar retains its current compact width.
- The task panel receives the remaining workspace width.
- The task table uses the full panel width with fixed column layout.
- A colgroup defines predictable proportions for Task, Assignees, Status, Priority, Tags, Schedule, Time, and Actions.
- The hard 1120px table minimum and 250px task-cell minimum no longer apply.
- Cell padding and compact controls are reduced slightly without changing functionality.
- Long task names truncate with an ellipsis and expose the complete text through the existing link/title context.
- Schedule may use two short lines inside its cell, but the task itself remains one table row.
- Timer, status, expand, edit, and add-subtask controls stay fully visible and clickable.

## Responsive Behavior

At viewport widths below 1200px, the workspace returns to the contained layout and the table regains a practical minimum width with horizontal scrolling. At 820px and below, the existing stacked project navigation remains unchanged.

This preserves readable columns on smaller screens instead of compressing them until labels and controls become unusable.

## Scope

The change is CSS and task-table markup only:

- Add a shared desktop breakout class to both admin and employee task workspaces.
- Add the same colgroup to both tables.
- Adjust shared table sizing, column widths, task-name truncation, and desktop/tablet media queries.
- Bump the stylesheet cache key.

No routes, task data, permissions, timers, subtasks, dialogs, or database structures change.

## Testing

Automated regression coverage will assert:

- Both workspaces use the desktop breakout class.
- Both tables define all eight shared columns.
- Desktop CSS removes the task table's forced minimum width and uses fixed layout.
- The below-1200px media query restores a minimum width and horizontal scrolling.
- Existing admin and employee workspace tests continue to pass.

Final verification will include Twig linting, the complete PHPUnit suite, CSS cache-key validation, and authenticated rendering of both workspaces.
