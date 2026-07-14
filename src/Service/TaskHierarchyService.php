<?php
namespace App\Service;

use App\Entity\Task;

final class TaskHierarchyService
{
    public function assertValidParent(Task $task, ?Task $parent): void
    {
        if (!$parent) { return; }
        if ($parent === $task) { throw new \DomainException('A task cannot be its own parent.'); }
        if ($task->getProject() !== $parent->getProject()) { throw new \DomainException('Parent and child must belong to the same project.'); }
        $visited = [];
        for ($node = $parent; $node; $node = $node->getParent()) {
            $key = spl_object_id($node);
            if (isset($visited[$key])) { throw new \DomainException('The task hierarchy contains a cycle.'); }
            if ($node === $task) { throw new \DomainException('A task cannot be moved beneath its descendant.'); }
            $visited[$key] = true;
        }
    }

    /** @param iterable<Task> $tasks @return list<array{task: Task, depth: int, has_children: bool}> */
    public function buildTree(iterable $tasks): array
    {
        $items = is_array($tasks) ? array_values($tasks) : iterator_to_array($tasks, false);
        $included = [];
        foreach ($items as $task) { $included[spl_object_id($task)] = true; }
        $roots = array_values(array_filter($items, static fn (Task $task): bool => !$task->getParent() || !isset($included[spl_object_id($task->getParent())])));
        $rows = []; $visited = [];
        foreach ($roots as $root) { $this->appendRows($root, 0, $included, $visited, $rows); }
        foreach ($items as $task) { $this->appendRows($task, 0, $included, $visited, $rows); }
        return $rows;
    }

    /** @param array<int,true> $included @param array<int,true> $visited @param list<array{task: Task, depth: int, has_children: bool}> $rows */
    private function appendRows(Task $task, int $depth, array $included, array &$visited, array &$rows): void
    {
        $key = spl_object_id($task);
        if (isset($visited[$key]) || !isset($included[$key])) { return; }
        $visited[$key] = true;
        $children = array_values(array_filter($task->getChildren()->toArray(), static fn (Task $child): bool => isset($included[spl_object_id($child)])));
        $rows[] = ['task' => $task, 'depth' => $depth, 'has_children' => $children !== []];
        foreach ($children as $child) { $this->appendRows($child, $depth + 1, $included, $visited, $rows); }
    }

    public function assertValidDependency(Task $task, Task $prerequisite): void
    {
        if ($task === $prerequisite) { throw new \DomainException('A task cannot depend on itself.'); }
        if ($task->getProject() !== $prerequisite->getProject()) { throw new \DomainException('Dependencies must belong to the same project.'); }
        if ($this->dependsOn($prerequisite, $task, [])) { throw new \DomainException('This dependency would create a cycle.'); }
    }

    /** @param array<int,true> $visited */
    private function dependsOn(Task $task, Task $target, array $visited): bool
    {
        if ($task === $target) { return true; }
        $key = spl_object_id($task);
        if (isset($visited[$key])) { return false; }
        $visited[$key] = true;
        foreach ($task->getDependencies() as $dependency) {
            $next = $dependency->getPrerequisite();
            if ($next && $this->dependsOn($next, $target, $visited)) { return true; }
        }
        return false;
    }
}
