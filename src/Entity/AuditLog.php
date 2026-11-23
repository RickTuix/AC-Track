<?php

namespace App\Entity;

use App\Repository\AuditLogRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AuditLogRepository::class)]
#[ORM\Table(name: 'audit_logs')]
#[ORM\Index(columns: ['action'], name: 'idx_audit_action')]
#[ORM\Index(columns: ['entity_type', 'entity_id'], name: 'idx_audit_entity')]
class AuditLog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'auditLogs', targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $user = null;

    #[ORM\Column(length: 100)]
    private ?string $action = null;

    #[ORM\Column(length: 100)]
    private ?string $entityType = null;

    #[ORM\Column]
    private ?int $entityId = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $oldValue = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $newValue = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $ipAddress = null;

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

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function getAction(): ?string
    {
        return $this->action;
    }

    public function setAction(string $action): static
    {
        $this->action = $action;

        return $this;
    }

    public function getEntityType(): ?string
    {
        return $this->entityType;
    }

    public function setEntityType(string $entityType): static
    {
        $this->entityType = $entityType;

        return $this;
    }

    public function getEntityId(): ?int
    {
        return $this->entityId;
    }

    public function setEntityId(int $entityId): static
    {
        $this->entityId = $entityId;

        return $this;
    }

    public function getOldValue(): ?array
    {
        return $this->oldValue;
    }

    public function setOldValue(?array $oldValue): static
    {
        $this->oldValue = $oldValue;

        return $this;
    }

    public function getNewValue(): ?array
    {
        return $this->newValue;
    }

    public function setNewValue(?array $newValue): static
    {
        $this->newValue = $newValue;

        return $this;
    }

    public function getIpAddress(): ?string
    {
        return $this->ipAddress;
    }

    public function setIpAddress(?string $ipAddress): static
    {
        $this->ipAddress = $ipAddress;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }
}
