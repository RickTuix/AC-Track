<?php

namespace App\Entity;

use App\Repository\TeacherProfileRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: TeacherProfileRepository::class)]
#[ORM\Table(name: 'teacher_profiles')]
#[UniqueEntity('employeeNumber', message: 'This employee number is already registered.')]
class TeacherProfile
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne(inversedBy: 'teacherProfile', targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\Column(length: 50, unique: true)]
    #[Assert\NotBlank]
    private ?string $employeeNumber = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank]
    private ?string $firstName = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank]
    private ?string $lastName = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $department = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $title = 'Prof.';

    /**
     * @var Collection<int, SectionSubject>
     */
    #[ORM\OneToMany(targetEntity: SectionSubject::class, mappedBy: 'teacher')]
    private Collection $sectionSubjects;

    public function __construct()
    {
        $this->sectionSubjects = new ArrayCollection();
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

    public function getEmployeeNumber(): ?string
    {
        return $this->employeeNumber;
    }

    public function setEmployeeNumber(string $employeeNumber): static
    {
        $this->employeeNumber = $employeeNumber;

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

    public function getDepartment(): ?string
    {
        return $this->department;
    }

    public function setDepartment(?string $department): static
    {
        $this->department = $department;

        return $this;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getFullName(): string
    {
        $prefix = $this->title ? $this->title . ' ' : '';
        return trim($prefix . $this->firstName . ' ' . $this->lastName);
    }

    /**
     * @return Collection<int, SectionSubject>
     */
    public function getSectionSubjects(): Collection
    {
        return $this->sectionSubjects;
    }

    public function __toString(): string
    {
        return $this->getFullName();
    }
}
