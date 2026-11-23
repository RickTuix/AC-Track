<?php

namespace App\Entity;

use App\Repository\SectionRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: SectionRepository::class)]
#[ORM\Table(name: 'sections')]
#[UniqueEntity('name', message: 'A section with this name already exists.')]
class Section
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100, unique: true)]
    #[Assert\NotBlank]
    private ?string $name = null;

    #[ORM\Column(length: 50)]
    #[Assert\NotBlank]
    private ?string $academicYear = null;

    #[ORM\Column(length: 50)]
    #[Assert\NotBlank]
    private ?string $semester = null;

    /**
     * @var Collection<int, StudentProfile>
     */
    #[ORM\OneToMany(targetEntity: StudentProfile::class, mappedBy: 'section')]
    private Collection $students;

    /**
     * @var Collection<int, SectionSubject>
     */
    #[ORM\OneToMany(targetEntity: SectionSubject::class, mappedBy: 'section', cascade: ['remove'], orphanRemoval: true)]
    private Collection $sectionSubjects;

    public function __construct()
    {
        $this->students = new ArrayCollection();
        $this->sectionSubjects = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getAcademicYear(): ?string
    {
        return $this->academicYear;
    }

    public function setAcademicYear(string $academicYear): static
    {
        $this->academicYear = $academicYear;

        return $this;
    }

    public function getSemester(): ?string
    {
        return $this->semester;
    }

    public function setSemester(string $semester): static
    {
        $this->semester = $semester;

        return $this;
    }

    /**
     * @return Collection<int, StudentProfile>
     */
    public function getStudents(): Collection
    {
        return $this->students;
    }

    public function addStudent(StudentProfile $student): static
    {
        if (!$this->students->contains($student)) {
            $this->students->add($student);
            $student->setSection($this);
        }

        return $this;
    }

    public function removeStudent(StudentProfile $student): static
    {
        if ($this->students->removeElement($student)) {
            if ($student->getSection() === $this) {
                $student->setSection(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, SectionSubject>
     */
    public function getSectionSubjects(): Collection
    {
        return $this->sectionSubjects;
    }

    public function addSectionSubject(SectionSubject $sectionSubject): static
    {
        if (!$this->sectionSubjects->contains($sectionSubject)) {
            $this->sectionSubjects->add($sectionSubject);
            $sectionSubject->setSection($this);
        }

        return $this;
    }

    public function removeSectionSubject(SectionSubject $sectionSubject): static
    {
        if ($this->sectionSubjects->removeElement($sectionSubject)) {
            if ($sectionSubject->getSection() === $this) {
                $sectionSubject->setSection(null);
            }
        }

        return $this;
    }

    public function __toString(): string
    {
        return $this->name ?? 'Section';
    }
}
