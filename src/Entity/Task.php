<?php

namespace App\Entity;

use App\Repository\TaskRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: TaskRepository::class)]
#[ORM\Table(name: 'tasks')]
class Task
{
    public const STATUS_TODO = 'todo';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_PAUSED = 'paused';
    public const STATUS_COMPLETED = 'completed';
    public const RECURRENCE_DAILY = 'daily';
    public const RECURRENCE_WEEKLY = 'weekly';
    public const RECURRENCE_MONTHLY = 'monthly';
    public const BILLING_BILLABLE = 'billable';
    public const BILLING_NON_BILLABLE = 'non_billable';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'tasks')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Project $project = null;

    #[ORM\ManyToOne(targetEntity: self::class, inversedBy: 'children')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?self $parent = null;

    /** @var Collection<int, Task> */
    #[ORM\OneToMany(mappedBy: 'parent', targetEntity: self::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['createdAt' => 'ASC'])]
    private Collection $children;

    /** @var Collection<int, User> */
    #[ORM\ManyToMany(targetEntity: User::class, inversedBy: 'tasks')]
    #[ORM\JoinTable(name: 'task_assignees')]
    private Collection $assignees;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $createdBy = null;

    #[ORM\Column(length: 180)]
    #[Assert\NotBlank]
    private string $title = '';

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 32)]
    private string $priority = 'normal';

    #[ORM\Column(length: 32)]
    private string $status = self::STATUS_TODO;

    #[ORM\Column(nullable: true)]
    private ?int $estimatedMinutes = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $startDate = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $dueDate = null;

    /** @var list<string> */
    #[ORM\Column(type: Types::JSON)]
    private array $tags = [];

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $reminderAt = null;

    #[ORM\Column(length: 16, nullable: true)]
    private ?string $recurrence = null;

    #[ORM\Column(length: 32)]
    private string $billingType = self::BILLING_BILLABLE;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $managerNote = null;

    #[ORM\OneToOne(targetEntity: self::class, inversedBy: 'nextOccurrence')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?self $sourceOccurrence = null;

    #[ORM\OneToOne(targetEntity: self::class, mappedBy: 'sourceOccurrence')]
    private ?self $nextOccurrence = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    /** @var Collection<int, TaskTimeEntry> */
    #[ORM\OneToMany(mappedBy: 'task', targetEntity: TaskTimeEntry::class, orphanRemoval: true)]
    private Collection $timeEntries;

    /** @var Collection<int, TaskComment> */
    #[ORM\OneToMany(mappedBy: 'task', targetEntity: TaskComment::class, cascade: ['persist'], orphanRemoval: true)]
    #[ORM\OrderBy(['createdAt' => 'ASC'])]
    private Collection $comments;

    /** @var Collection<int, TaskDocument> */
    #[ORM\OneToMany(mappedBy: 'task', targetEntity: TaskDocument::class, cascade: ['persist'], orphanRemoval: true)]
    private Collection $documents;

    /** @var Collection<int, TaskDependency> */
    #[ORM\OneToMany(mappedBy: 'task', targetEntity: TaskDependency::class, cascade: ['persist'], orphanRemoval: true)]
    private Collection $dependencies;

    /** @var Collection<int, TaskProblem> */
    #[ORM\OneToMany(mappedBy: 'task', targetEntity: TaskProblem::class, cascade: ['persist'], orphanRemoval: true)]
    #[ORM\OrderBy(['createdAt' => 'DESC'])]
    private Collection $problems;

    /** @var Collection<int, TaskStatusHistory> */
    #[ORM\OneToMany(mappedBy: 'task', targetEntity: TaskStatusHistory::class, cascade: ['persist'], orphanRemoval: true)]
    #[ORM\OrderBy(['createdAt' => 'DESC'])]
    private Collection $statusHistory;

    /** @var Collection<int, TaskActivity> */
    #[ORM\OneToMany(mappedBy: 'task', targetEntity: TaskActivity::class, cascade: ['persist'], orphanRemoval: true)]
    #[ORM\OrderBy(['createdAt' => 'DESC'])]
    private Collection $activities;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->children = new ArrayCollection();
        $this->assignees = new ArrayCollection();
        $this->timeEntries = new ArrayCollection();
        $this->comments = new ArrayCollection();
        $this->documents = new ArrayCollection();
        $this->dependencies = new ArrayCollection();
        $this->problems = new ArrayCollection();
        $this->statusHistory = new ArrayCollection();
        $this->activities = new ArrayCollection();
    }

    public function __toString(): string { return $this->title; }
    public function getId(): ?int { return $this->id; }
    public function getProject(): ?Project { return $this->project; }

    public function setProject(?Project $project): self
    {
        if ($project && $this->parent?->getProject() && $project !== $this->parent->getProject()) {
            throw new \DomainException('A subtask must belong to the same project as its parent.');
        }
        $this->project = $project;
        return $this;
    }

    public function getParent(): ?self { return $this->parent; }

    public function setParent(?self $parent): self
    {
        if ($parent === $this) {
            throw new \DomainException('A task cannot be its own parent.');
        }
        if ($parent && $this->project && $parent->getProject() && $this->project !== $parent->getProject()) {
            throw new \DomainException('A subtask must belong to the same project as its parent.');
        }
        $this->parent = $parent;
        return $this;
    }

    /** @return Collection<int, Task> */
    public function getChildren(): Collection { return $this->children; }

    public function addChild(self $child): self
    {
        if (!$this->children->contains($child)) {
            $this->children->add($child);
            $child->setParent($this);
        }
        return $this;
    }

    public function removeChild(self $child): self
    {
        if ($this->children->removeElement($child) && $child->getParent() === $this) {
            $child->setParent(null);
        }
        return $this;
    }

    /** @return Collection<int, User> */
    public function getAssignees(): Collection { return $this->assignees; }

    public function addAssignee(User $assignee): self
    {
        if (!$this->assignees->contains($assignee)) {
            $this->assignees->add($assignee);
            $assignee->addTask($this);
        }
        return $this;
    }

    public function removeAssignee(User $assignee): self
    {
        if ($this->assignees->removeElement($assignee)) {
            $assignee->removeTask($this);
        }
        return $this;
    }

    public function isAssignedTo(User $user): bool { return $this->assignees->contains($user); }
    public function getCreatedBy(): ?User { return $this->createdBy; }
    public function setCreatedBy(?User $createdBy): self { $this->createdBy = $createdBy; return $this; }
    public function getTitle(): string { return $this->title; }
    public function setTitle(string $title): self { $this->title = trim($title); return $this; }
    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $description): self { $this->description = $description ? trim($description) : null; return $this; }
    public function getPriority(): string { return $this->priority; }
    public function setPriority(string $priority): self { $this->priority = $priority; return $this; }
    public function getStatus(): string { return $this->status; }

    public function setStatus(string $status): self
    {
        $allowed = [self::STATUS_TODO, self::STATUS_IN_PROGRESS, self::STATUS_PAUSED, self::STATUS_COMPLETED];
        $this->status = in_array($status, $allowed, true) ? $status : self::STATUS_TODO;
        return $this;
    }

    public function getEstimatedMinutes(): ?int { return $this->estimatedMinutes; }
    public function setEstimatedMinutes(?int $estimatedMinutes): self { $this->estimatedMinutes = $estimatedMinutes; return $this; }
    public function getStartDate(): ?\DateTimeImmutable { return $this->startDate; }

    public function setStartDate(?\DateTimeImmutable $startDate): self
    {
        if ($startDate && $this->dueDate && $this->dueDate < $startDate) {
            throw new \DomainException('The start date cannot be after the due date.');
        }
        $this->startDate = $startDate;
        return $this;
    }

    public function getDueDate(): ?\DateTimeImmutable { return $this->dueDate; }

    public function setDueDate(?\DateTimeImmutable $dueDate): self
    {
        if ($dueDate && $this->startDate && $dueDate < $this->startDate) {
            throw new \DomainException('The due date cannot be before the start date.');
        }
        $this->dueDate = $dueDate;
        return $this;
    }

    /** @return list<string> */
    public function getTags(): array { return $this->tags; }

    /** @param iterable<mixed> $tags */
    public function setTags(iterable $tags): self
    {
        $normalized = [];
        $seen = [];
        foreach ($tags as $tag) {
            if (!is_scalar($tag)) { continue; }
            $label = trim((string) $tag);
            $key = strtolower($label);
            if ($label === '' || isset($seen[$key])) { continue; }
            $seen[$key] = true;
            $normalized[] = substr($label, 0, 50);
        }
        $this->tags = array_slice($normalized, 0, 20);
        return $this;
    }

    public function getReminderAt(): ?\DateTimeImmutable { return $this->reminderAt; }
    public function setReminderAt(?\DateTimeImmutable $reminderAt): self { $this->reminderAt = $reminderAt; return $this; }
    public function getRecurrence(): ?string { return $this->recurrence; }

    public function setRecurrence(?string $recurrence): self
    {
        $allowed = [self::RECURRENCE_DAILY, self::RECURRENCE_WEEKLY, self::RECURRENCE_MONTHLY];
        $this->recurrence = in_array($recurrence, $allowed, true) ? $recurrence : null;
        return $this;
    }

    public function getBillingType(): string { return $this->billingType; }

    public function setBillingType(string $billingType): self
    {
        $this->billingType = in_array($billingType, [self::BILLING_BILLABLE, self::BILLING_NON_BILLABLE], true)
            ? $billingType : self::BILLING_BILLABLE;
        return $this;
    }

    public function getManagerNote(): ?string { return $this->managerNote; }
    public function setManagerNote(?string $managerNote): self { $managerNote = $managerNote !== null ? trim($managerNote) : null; $this->managerNote = $managerNote ?: null; return $this; }
    public function getSourceOccurrence(): ?self { return $this->sourceOccurrence; }
    public function setSourceOccurrence(?self $sourceOccurrence): self { $this->sourceOccurrence = $sourceOccurrence; if ($sourceOccurrence && $sourceOccurrence->getNextOccurrence() !== $this) { $sourceOccurrence->setNextOccurrence($this); } return $this; }
    public function getNextOccurrence(): ?self { return $this->nextOccurrence; }
    public function setNextOccurrence(?self $nextOccurrence): self { $this->nextOccurrence = $nextOccurrence; if ($nextOccurrence && $nextOccurrence->getSourceOccurrence() !== $this) { $nextOccurrence->setSourceOccurrence($this); } return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    /** @return Collection<int, TaskTimeEntry> */
    public function getTimeEntries(): Collection { return $this->timeEntries; }
    /** @return Collection<int, TaskComment> */
    public function getComments(): Collection { return $this->comments; }

    public function addComment(TaskComment $comment): self
    {
        if (!$this->comments->contains($comment)) { $this->comments->add($comment); $comment->setTask($this); }
        return $this;
    }

    public function removeComment(TaskComment $comment): self
    {
        if ($this->comments->removeElement($comment) && $comment->getTask() === $this) { $comment->setTask(null); }
        return $this;
    }

    /** @return Collection<int, TaskDocument> */
    public function getDocuments(): Collection { return $this->documents; }
    public function addDocument(TaskDocument $document): self { if (!$this->documents->contains($document)) { $this->documents->add($document); $document->setTask($this); } return $this; }
    public function removeDocument(TaskDocument $document): self { if ($this->documents->removeElement($document) && $document->getTask() === $this) { $document->setTask(null); } return $this; }
    /** @return Collection<int, TaskDependency> */
    public function getDependencies(): Collection { return $this->dependencies; }
    public function addDependency(TaskDependency $dependency): self { if (!$this->dependencies->contains($dependency)) { $this->dependencies->add($dependency); $dependency->setTask($this); } return $this; }
    /** @return Collection<int, TaskProblem> */
    public function getProblems(): Collection { return $this->problems; }
    public function addProblem(TaskProblem $problem): self { if (!$this->problems->contains($problem)) { $this->problems->add($problem); $problem->setTask($this); } return $this; }
    /** @return Collection<int, TaskStatusHistory> */
    public function getStatusHistory(): Collection { return $this->statusHistory; }
    public function addStatusHistory(TaskStatusHistory $history): self { if (!$this->statusHistory->contains($history)) { $this->statusHistory->add($history); $history->setTask($this); } return $this; }
    /** @return Collection<int, TaskActivity> */
    public function getActivities(): Collection { return $this->activities; }
    public function addActivity(TaskActivity $activity): self { if (!$this->activities->contains($activity)) { $this->activities->add($activity); $activity->setTask($this); } return $this; }

    public function getTrackedSeconds(): int
    {
        return array_sum($this->timeEntries->map(static fn (TaskTimeEntry $entry): int => $entry->getSeconds())->toArray());
    }

    public function getCompletionPercentage(): int
    {
        $leaves = [];
        $this->collectLeaves($this, $leaves, []);
        if ($leaves === []) { return $this->status === self::STATUS_COMPLETED ? 100 : 0; }
        $completed = count(array_filter($leaves, static fn (self $task): bool => $task->getStatus() === self::STATUS_COMPLETED));
        return (int) round(($completed / count($leaves)) * 100);
    }

    /** @param list<Task> $leaves @param array<int, true> $visited */
    private function collectLeaves(self $task, array &$leaves, array $visited): void
    {
        $key = spl_object_id($task);
        if (isset($visited[$key])) { return; }
        $visited[$key] = true;
        if ($task->children->isEmpty()) {
            if ($task !== $this) { $leaves[] = $task; }
            return;
        }
        foreach ($task->children as $child) { $this->collectLeaves($child, $leaves, $visited); }
    }
}
