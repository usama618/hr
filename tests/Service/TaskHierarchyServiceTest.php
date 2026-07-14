<?php
namespace App\Tests\Service;

use App\Entity\Project;
use App\Entity\Task;
use App\Entity\TaskDependency;
use App\Service\TaskHierarchyService;
use PHPUnit\Framework\TestCase;

final class TaskHierarchyServiceTest extends TestCase
{
    public function testBuildTreeFlattensUnlimitedDepth(): void
    {
        $project = (new Project())->setName('P');
        $root = (new Task())->setProject($project)->setTitle('Root');
        $child = (new Task())->setProject($project)->setTitle('Child');
        $leaf = (new Task())->setProject($project)->setTitle('Leaf');
        $root->addChild($child); $child->addChild($leaf);

        $rows = (new TaskHierarchyService())->buildTree([$leaf, $root, $child]);

        self::assertSame(['Root', 'Child', 'Leaf'], array_map(fn ($row) => $row['task']->getTitle(), $rows));
        self::assertSame([0, 1, 2], array_column($rows, 'depth'));
        self::assertSame([true, true, false], array_column($rows, 'has_children'));
    }

    public function testRejectsCrossProjectAndDescendantParents(): void
    {
        $a = (new Project())->setName('A'); $b = (new Project())->setName('B');
        $root = (new Task())->setProject($a); $child = (new Task())->setProject($a); $root->addChild($child);
        $service = new TaskHierarchyService();

        try { $service->assertValidParent($root, $child); self::fail('Cycle accepted'); } catch (\DomainException) {}
        $this->expectException(\DomainException::class);
        $service->assertValidParent($root, (new Task())->setProject($b));
    }

    public function testRejectsDependencyCycle(): void
    {
        $project = (new Project())->setName('P');
        $a = (new Task())->setProject($project); $b = (new Task())->setProject($project);
        $b->addDependency((new TaskDependency())->setPrerequisite($a));

        $this->expectException(\DomainException::class);
        (new TaskHierarchyService())->assertValidDependency($a, $b);
    }
}
