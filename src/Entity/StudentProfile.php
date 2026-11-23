<?php

namespace App\Entity;

use App\Repository\StudentProfileRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: StudentProfileRepository::class)]
#[ORM\Table(name: 'student_profiles')]
#[UniqueEntity('studentNumber', message: 'This student number is already registered.')]
class StudentProfile
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne(inversedBy: 'studentProfile', targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\Column(length: 50, unique: true)]
    #[Assert\NotBlank]
    private ?string $studentNumber = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank]
    private ?string $firstName = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank]
    private ?string $lastName = null;

    #[ORM\Column(length: 50)]
    #[Assert\NotBlank]
    private ?string $yearLevel = '1st Year';

    #[ORM\ManyToOne(inversedBy: 'students', targetEntity: Section::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Section $section = null;

    /**
     * @var Collection<int, Grade>
     */
    #[ORM\OneToMany(targetEntity: Grade::class, mappedBy: 'student', cascade: ['remove'])]
    private Collection $grades;

    /**
     * @var Collection<int, IncompleteRecord>
     */
    #[ORM\OneToMany(targetEntity: IncompleteRecord::class, mappedBy: 'student', cascade: ['remove'])]
    private Collection $incompleteRecords;

    public function __construct()
    {
        $this->grades = new ArrayCollection();
        $this->incompleteRecords = new ArrayCollection();
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

    public function getStudentNumber(): ?string
    {
        return $this->studentNumber;
    }

    public function setStudentNumber(string $studentNumber): static
    {
        $this->studentNumber = $studentNumber;

        return $this;
    }

    public function getFirstName(): ?string
    {
        return $this->firstName;
    }

    public function setFirstName(string $firstName): static
    {
        $this->firstName = $firstName;

        return $this;
    }

    public function getLastName(): ?string
    {
        return $this->lastName;
    }

    public function setLastName(string $lastName): static
    {
        $this->lastName = $lastName;

        return $this;
    }

    public function getYearLevel(): ?string
    {
        return $this->yearLevel;
    }

    public function setYearLevel(string $yearLevel): static
    {
        $this->yearLevel = $yearLevel;

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

    /**
     * @return Collection<int, Grade>
     */
    public function getGrades(): Collection
    {
        return $this->grades;
    }

    /**
     * @return Collection<int, IncompleteRecord>
     */
    public function getIncompleteRecords(): Collection
    {
        return $this->incompleteRecords;
    }

    public function getFullName(): string
    {
        return trim($this->firstName . ' ' . $this->lastName);
    }

    public function __toString(): string
    {
        return sprintf('%s (%s)', $this->getFullName(), $this->studentNumber);
    }
}
