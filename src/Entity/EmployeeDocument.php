<?php

namespace App\Entity;

use App\Repository\EmployeeDocumentRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: EmployeeDocumentRepository::class)]
#[ORM\Table(name: 'employee_documents')]
class EmployeeDocument
{
    public const CATEGORY_POLICY = 'policy';
    public const CATEGORY_CONTRACT = 'contract';
    public const CATEGORY_PAYSLIP = 'payslip';
    public const CATEGORY_CERTIFICATE = 'certificate';
    public const CATEGORY_OTHER = 'other';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?User $owner = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $uploadedBy = null;

    #[ORM\Column(length: 180)]
    private string $title = '';

    #[ORM\Column(length: 40)]
    private string $category = self::CATEGORY_OTHER;

    #[ORM\Column(length: 255)]
    private string $originalFilename = '';

    #[ORM\Column(length: 255)]
    private string $storedFilename = '';

    #[ORM\Column(length: 120)]
    private string $mimeType = 'application/octet-stream';

    #[ORM\Column]
    private int $fileSize = 0;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOwner(): ?User
    {
        return $this->owner;
    }

    public function setOwner(?User $owner): self
    {
        $this->owner = $owner;

        return $this;
    }

    public function getUploadedBy(): ?User
    {
        return $this->uploadedBy;
    }

    public function setUploadedBy(?User $uploadedBy): self
    {
        $this->uploadedBy = $uploadedBy;

        return $this;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): self
    {
        $this->title = substr(trim($title), 0, 180);

        return $this;
    }

    public function getCategory(): string
    {
        return $this->category;
    }

    public function setCategory(string $category): self
    {
        $allowed = [
            self::CATEGORY_POLICY,
            self::CATEGORY_CONTRACT,
            self::CATEGORY_PAYSLIP,
            self::CATEGORY_CERTIFICATE,
            self::CATEGORY_OTHER,
        ];
        $this->category = in_array($category, $allowed, true) ? $category : self::CATEGORY_OTHER;

        return $this;
    }

    public function getOriginalFilename(): string
    {
        return $this->originalFilename;
    }

    public function setOriginalFilename(string $originalFilename): self
    {
        $this->originalFilename = substr(trim($originalFilename), 0, 255);

        return $this;
    }

    public function getStoredFilename(): string
    {
        return $this->storedFilename;
    }

    public function setStoredFilename(string $storedFilename): self
    {
        $this->storedFilename = substr(trim($storedFilename), 0, 255);

        return $this;
    }

    public function getMimeType(): string
    {
        return $this->mimeType;
    }

    public function setMimeType(string $mimeType): self
    {
        $this->mimeType = substr(trim($mimeType) ?: 'application/octet-stream', 0, 120);

        return $this;
    }

    public function getFileSize(): int
    {
        return $this->fileSize;
    }

    public function setFileSize(int $fileSize): self
    {
        $this->fileSize = max(0, $fileSize);

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $description = $description !== null ? trim($description) : null;
        $this->description = $description ?: null;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function isCompanyWide(): bool
    {
        return $this->owner === null;
    }
}
