<?php

namespace App\Entity;

use App\Repository\SubjectRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: SubjectRepository::class)]
#[ORM\Table(name: 'subjects')]
#[UniqueEntity('code', message: 'A subject with this code already exists.')]
class Subject
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 20, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 20)]
    private ?string $code = null;

    #[ORM\Column(length: 150)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 150)]
    private ?string $title = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column]
    #[Assert\NotNull]
    #[Assert\Positive]
    private ?int $units = 3;

    /**
     * @var Collection<int, SectionSubject>
     */
    #[ORM\OneToMany(targetEntity: SectionSubject::class, mappedBy: 'subject', cascade: ['remove'], orphanRemoval: true)]
    private Collection $sectionSubjects;

    public function __construct()
    {
        $this->sectionSubjects = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(string $code): static
    {
        $this->code = strtoupper(trim($code));

        return $this;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getUnits(): ?int
    {
        return $this->units;
    }

    public function setUnits(int $units): static
    {
        $this->units = $units;

        return $this;
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
        return sprintf('%s - %s', $this->code, $this->title);
    }
}
