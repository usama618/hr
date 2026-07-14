<?php
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'task_documents')]
class TaskDocument
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column] private ?int $id = null;
    #[ORM\ManyToOne(inversedBy: 'documents')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Task $task = null;
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $uploadedBy = null;
    #[ORM\Column(length: 255)] private string $originalFilename = '';
    #[ORM\Column(length: 255)] private string $storedFilename = '';
    #[ORM\Column(length: 120)] private string $mimeType = 'application/octet-stream';
    #[ORM\Column] private int $fileSize = 0;
    #[ORM\Column] private \DateTimeImmutable $createdAt;
    public function __construct() { $this->createdAt = new \DateTimeImmutable(); }
    public function getId(): ?int { return $this->id; }
    public function getTask(): ?Task { return $this->task; }
    public function setTask(?Task $task): self { $this->task = $task; return $this; }
    public function getUploadedBy(): ?User { return $this->uploadedBy; }
    public function setUploadedBy(?User $user): self { $this->uploadedBy = $user; return $this; }
    public function getOriginalFilename(): string { return $this->originalFilename; }
    public function setOriginalFilename(string $value): self { $this->originalFilename = substr(trim($value), 0, 255); return $this; }
    public function getStoredFilename(): string { return $this->storedFilename; }
    public function setStoredFilename(string $value): self { $this->storedFilename = substr(trim($value), 0, 255); return $this; }
    public function getMimeType(): string { return $this->mimeType; }
    public function setMimeType(string $value): self { $this->mimeType = substr(trim($value) ?: 'application/octet-stream', 0, 120); return $this; }
    public function getFileSize(): int { return $this->fileSize; }
    public function setFileSize(int $value): self { $this->fileSize = max(0, $value); return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
}
