<?php

namespace App\Entity;

use App\Enum\IncompleteStatus;
use App\Repository\IncompleteRecordRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: IncompleteRecordRepository::class)]
#[ORM\Table(name: 'incomplete_records')]
class IncompleteRecord
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'incompleteRecords', targetEntity: StudentProfile::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?StudentProfile $student = null;

    #[ORM\ManyToOne(targetEntity: Subject::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Subject $subject = null;

    #[ORM\ManyToOne(targetEntity: Section::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Section $section = null;

    #[ORM\ManyToOne(targetEntity: GradingPeriod::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?GradingPeriod $gradingPeriod = null;

    #[ORM\ManyToOne(targetEntity: TeacherProfile::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?TeacherProfile $teacher = null;

    #[ORM\Column(length: 50)]
    #[Assert\NotBlank]
    private ?string $missingCategory = 'Activity'; // Activity, Assignment, Project, Examination, Other

    #[ORM\Column(length: 200)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 200)]
    private ?string $missingItemTitle = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank]
    private ?string $teacherExplanation = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank]
    private ?string $requiredAction = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Assert\NotNull]
    private ?\DateTimeInterface $deadline = null;

    #[ORM\Column(type: 'string', length: 20, enumType: IncompleteStatus::class)]
    private IncompleteStatus $status = IncompleteStatus::PENDING;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $submissionNote = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $attachmentPath = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $attachmentOriginalName = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $submittedAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $resolvedAt = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $createdBy = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $updatedBy = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
        $this->status = IncompleteStatus::PENDING;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getStudent(): ?StudentProfile
    {
        return $this->student;
    }

    public function setStudent(?StudentProfile $student): static
    {
        $this->student = $student;

        return $this;
    }

    public function getSubject(): ?Subject
    {
        return $this->subject;
    }

    public function setSubject(?Subject $subject): static
    {
        $this->subject = $subject;

        return $this;
    }

    public function getSection(): ?Section
    {
        return $this->section;
    }

    public function setSection(?Section $section): static
    {
        $this->section = $section;

        return $this;
    }

    public function getGradingPeriod(): ?GradingPeriod
    {
        return $this->gradingPeriod;
    }

    public function setGradingPeriod(?GradingPeriod $gradingPeriod): static
    {
        $this->gradingPeriod = $gradingPeriod;

        return $this;
    }

    public function getTeacher(): ?TeacherProfile
    {
        return $this->teacher;
    }

    public function setTeacher(?TeacherProfile $teacher): static
    {
        $this->teacher = $teacher;

        return $this;
    }

    public function getMissingCategory(): ?string
    {
        return $this->missingCategory;
    }

    public function setMissingCategory(string $missingCategory): static
    {
        $this->missingCategory = $missingCategory;

        return $this;
    }

    public function getMissingItemTitle(): ?string
    {
        return $this->missingItemTitle;
    }

    public function setMissingItemTitle(string $missingItemTitle): static
    {
        $this->missingItemTitle = $missingItemTitle;

        return $this;
    }

    public function getTeacherExplanation(): ?string
    {
        return $this->teacherExplanation;
    }

    public function setTeacherExplanation(string $teacherExplanation): static
    {
        $this->teacherExplanation = $teacherExplanation;

        return $this;
    }

    public function getRequiredAction(): ?string
    {
        return $this->requiredAction;
    }

    public function setRequiredAction(string $requiredAction): static
    {
        $this->requiredAction = $requiredAction;

        return $this;
    }

    public function getDeadline(): ?\DateTimeInterface
    {
        return $this->deadline;
    }

    public function setDeadline(\DateTimeInterface $deadline): static
    {
        $this->deadline = $deadline;

        return $this;
    }

    public function getStatus(): IncompleteStatus
    {
        return $this->status;
    }

    public function setStatus(IncompleteStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getSubmissionNote(): ?string
    {
        return $this->submissionNote;
    }

    public function setSubmissionNote(?string $submissionNote): static
    {
        $this->submissionNote = $submissionNote;

        return $this;
    }

    public function getAttachmentPath(): ?string
    {
        return $this->attachmentPath;
    }

    public function setAttachmentPath(?string $attachmentPath): static
    {
        $this->attachmentPath = $attachmentPath;

        return $this;
    }

    public function getAttachmentOriginalName(): ?string
    {
        return $this->attachmentOriginalName;
    }

    public function setAttachmentOriginalName(?string $attachmentOriginalName): static
    {
        $this->attachmentOriginalName = $attachmentOriginalName;

        return $this;
    }

    public function getSubmittedAt(): ?\DateTimeInterface
    {
        return $this->submittedAt;
    }

    public function setSubmittedAt(?\DateTimeInterface $submittedAt): static
    {
        $this->submittedAt = $submittedAt;

        return $this;
    }

    public function getResolvedAt(): ?\DateTimeInterface
    {
        return $this->resolvedAt;
    }

    public function setResolvedAt(?\DateTimeInterface $resolvedAt): static
    {
        $this->resolvedAt = $resolvedAt;

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

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    public function getCreatedBy(): ?User
    {
        return $this->createdBy;
    }

    public function setCreatedBy(?User $createdBy): static
    {
        $this->createdBy = $createdBy;

        return $this;
    }

    public function getUpdatedBy(): ?User
    {
        return $this->updatedBy;
    }

    public function setUpdatedBy(?User $updatedBy): static
    {
        $this->updatedBy = $updatedBy;

        return $this;
    }

    public function isOverdue(): bool
    {
        if ($this->status === IncompleteStatus::COMPLETED) {
            return false;
        }

        return $this->deadline !== null && $this->deadline < new \DateTime();
    }

    public function isApproachingDeadline(int $hours = 48): bool
    {
        if ($this->status !== IncompleteStatus::PENDING || $this->deadline === null) {
            return false;
        }

        $now = new \DateTime();
        $diff = $this->deadline->getTimestamp() - $now->getTimestamp();

        return $diff > 0 && $diff <= ($hours * 3600);
    }
}
