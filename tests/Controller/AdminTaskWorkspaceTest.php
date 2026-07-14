<?php
namespace App\Tests\Controller;

use PHPUnit\Framework\TestCase;

final class AdminTaskWorkspaceTest extends TestCase
{
    public function testWorkspaceTemplateContainsProjectNavigatorAndTaskTree(): void
    {
        $template = file_get_contents(dirname(__DIR__, 2).'/templates/admin/tasks.html.twig');
        $rows = file_get_contents(dirname(__DIR__, 2).'/templates/admin/_task_tree_rows.html.twig');
        self::assertIsString($template);
        self::assertIsString($rows);
        $template .= $rows;
        self::assertStringContainsString('task-project-nav', $template);
        self::assertStringContainsString('role="treegrid"', $template);
        self::assertStringContainsString("data-task-toggle", $template);
        self::assertStringContainsString("admin_task_new", $template);
        self::assertStringContainsString("admin_task_show", $template);
        self::assertStringContainsString('data-task-row-url', $template);
        self::assertStringContainsString('task-timer-control', $template);
        self::assertStringContainsString('admin_task_detail_timer_start', $template);
        self::assertStringContainsString('admin_task_detail_timer_stop', $template);
    }

    public function testWorkspaceScriptMakesRowsClickableWithoutHijackingControls(): void
    {
        $base = file_get_contents(dirname(__DIR__, 2).'/templates/base.html.twig');
        self::assertIsString($base);
        self::assertStringContainsString("[data-task-row-url]", $base);
        self::assertStringContainsString("closest('a, button, input, select, textarea, form')", $base);
        self::assertStringContainsString("styles.css') }}?v=waldbyte-hr-19", $base);
    }

    public function testTaskFormExposesRichFieldsAndMultipleAssignees(): void
    {
        $form = file_get_contents(dirname(__DIR__, 2).'/src/Form/TaskFormType.php');
        self::assertIsString($form);
        foreach (['assignees', 'startDate', 'dueDate', 'tags', 'reminderAt', 'recurrence', 'billingType', 'managerNote'] as $field) {
            self::assertStringContainsString("->add('{$field}'", $form);
        }
        self::assertStringContainsString("'multiple' => true", $form);
    }
}
