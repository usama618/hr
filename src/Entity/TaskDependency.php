<?php
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'task_dependencies')]
#[ORM\UniqueConstraint(name: 'uniq_task_prerequisite', columns: ['task_id', 'prerequisite_id'])]
class TaskDependency
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column] private ?int $id = null;
    #[ORM\ManyToOne(inversedBy: 'dependencies')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Task $task = null;
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Task $prerequisite = null;
    #[ORM\Column] private \DateTimeImmutable $createdAt;
    public function __construct() { $this->createdAt = new \DateTimeImmutable(); }
    public function getId(): ?int { return $this->id; }
    public function getTask(): ?Task { return $this->task; }
    public function setTask(?Task $task): self { if ($task && $task === $this->prerequisite) { throw new \DomainException('A task cannot depend on itself.'); } $this->task = $task; return $this; }
    public function getPrerequisite(): ?Task { return $this->prerequisite; }
    public function setPrerequisite(Task $task): self { if ($task === $this->task) { throw new \DomainException('A task cannot depend on itself.'); } $this->prerequisite = $task; return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
}
