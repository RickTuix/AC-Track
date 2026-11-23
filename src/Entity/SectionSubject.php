<?php

namespace App\Entity;

use App\Repository\SectionSubjectRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

#[ORM\Entity(repositoryClass: SectionSubjectRepository::class)]
#[ORM\Table(name: 'section_subjects')]
#[UniqueEntity(fields: ['section', 'subject'], message: 'This subject is already assigned to this section.')]
class SectionSubject
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'sectionSubjects', targetEntity: Section::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Section $section = null;

    #[ORM\ManyToOne(inversedBy: 'sectionSubjects', targetEntity: Subject::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Subject $subject = null;

    #[ORM\ManyToOne(inversedBy: 'sectionSubjects', targetEntity: TeacherProfile::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?TeacherProfile $teacher = null;

    public function getId(): ?int
    {
        return $this->id;
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

    public function getSubject(): ?Subject
    {
        return $this->subject;
    }

    public function setSubject(?Subject $subject): static
    {
        $this->subject = $subject;

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

    public function __toString(): string
    {
        return sprintf(
            '%s - %s (%s)',
            $this->section?->getName() ?? '',
            $this->subject?->getCode() ?? '',
            $this->teacher?->getFullName() ?? 'Unassigned'
        );
    }
}
