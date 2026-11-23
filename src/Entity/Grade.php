<?php

namespace App\Entity;

use App\Repository\GradeRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: GradeRepository::class)]
#[ORM\Table(name: 'grades')]
#[UniqueEntity(fields: ['student', 'subject', 'gradingPeriod'], message: 'A grade for this student, subject, and period already exists.')]
class Grade
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'grades', targetEntity: StudentProfile::class)]
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

    #[ORM\Column(type: Types::DECIMAL, precision: 5, scale: 2, nullable: true)]
    #[Assert\Range(min: 0, max: 100)]
    private ?string $numericalValue = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $remarks = null;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $isInc = false;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $updatedBy = null;

    public function __construct()
    {
        $this->updatedAt = new \DateTimeImmutable();
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

    public function getNumericalValue(): ?string
    {
        return $this->numericalValue;
    }

    public function setNumericalValue(?string $numericalValue): static
    {
        $this->numericalValue = $numericalValue;

        return $this;
    }

    public function getRemarks(): ?string
    {
        return $this->remarks;
    }

    public function setRemarks(?string $remarks): static
    {
        $this->remarks = $remarks;

        return $this;
    }

    public function isInc(): bool
    {
        return $this->isInc;
    }

    public function setIsInc(bool $isInc): static
    {
        $this->isInc = $isInc;

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

    public function getUpdatedBy(): ?User
    {
        return $this->updatedBy;
    }

    public function setUpdatedBy(?User $updatedBy): static
    {
        $this->updatedBy = $updatedBy;

        return $this;
    }

    public function getDisplayGrade(): string
    {
        if ($this->isInc) {
            return 'INC';
        }

        return $this->numericalValue !== null ? number_format((float) $this->numericalValue, 2) : 'N/A';
    }
}
