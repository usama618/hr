<?php

namespace App\Entity;

use App\Repository\AttendanceEntryRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AttendanceEntryRepository::class)]
#[ORM\Table(name: 'attendance_entries')]
class AttendanceEntry
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $employee = null;

    #[ORM\Column]
    private \DateTimeImmutable $checkInAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $checkOutAt = null;

    /**
     * @var Collection<int, BreakEntry>
     */
    #[ORM\OneToMany(mappedBy: 'attendance', targetEntity: BreakEntry::class, cascade: ['persist'], orphanRemoval: true)]
    private Collection $breaks;

    public function __construct()
    {
        $this->checkInAt = new \DateTimeImmutable();
        $this->breaks = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmployee(): ?User
    {
        return $this->employee;
    }

    public function setEmployee(?User $employee): self
    {
        $this->employee = $employee;

        return $this;
    }

    public function getCheckInAt(): \DateTimeImmutable
    {
        return $this->checkInAt;
    }

    public function setCheckInAt(\DateTimeImmutable $checkInAt): self
    {
        $this->checkInAt = $checkInAt;

        return $this;
    }

    public function getCheckOutAt(): ?\DateTimeImmutable
    {
        return $this->checkOutAt;
    }

    public function setCheckOutAt(?\DateTimeImmutable $checkOutAt): self
    {
        $this->checkOutAt = $checkOutAt;

        return $this;
    }

    /**
     * @return Collection<int, BreakEntry>
     */
    public function getBreaks(): Collection
    {
        return $this->breaks;
    }

    public function addBreak(BreakEntry $break): self
    {
        if (!$this->breaks->contains($break)) {
            $this->breaks->add($break);
            $break->setAttendance($this);
        }

        return $this;
    }

    public function getBreakSeconds(): int
    {
        return array_sum($this->breaks->map(static fn (BreakEntry $break): int => $break->getSeconds())->toArray());
    }

    public function getWorkedSeconds(): int
    {
        $end = $this->checkOutAt ?? new \DateTimeImmutable();
        $total = $end->getTimestamp() - $this->checkInAt->getTimestamp();

        return max(0, $total - $this->getBreakSeconds());
    }
}
