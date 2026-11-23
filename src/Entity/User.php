<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: 'users')]
#[UniqueEntity(fields: ['username'], message: 'There is already an account with this username')]
#[UniqueEntity(fields: ['email'], message: 'There is already an account with this email')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Length(min: 3, max: 180)]
    private ?string $username = null;

    #[ORM\Column(length: 180, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Email]
    private ?string $email = null;

    /**
     * @var list<string> The user roles
     */
    #[ORM\Column]
    private array $roles = [];

    /**
     * @var string The hashed password
     */
    #[ORM\Column]
    private ?string $password = null;

    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    private bool $isActive = true;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\OneToOne(mappedBy: 'user', targetEntity: StudentProfile::class, cascade: ['persist', 'remove'])]
    private ?StudentProfile $studentProfile = null;

    #[ORM\OneToOne(mappedBy: 'user', targetEntity: TeacherProfile::class, cascade: ['persist', 'remove'])]
    private ?TeacherProfile $teacherProfile = null;

    /**
     * @var Collection<int, Notification>
     */
    #[ORM\OneToMany(targetEntity: Notification::class, mappedBy: 'user', cascade: ['remove'])]
    #[ORM\OrderBy(['createdAt' => 'DESC'])]
    private Collection $notifications;

    /**
     * @var Collection<int, AuditLog>
     */
    #[ORM\OneToMany(targetEntity: AuditLog::class, mappedBy: 'user')]
    private Collection $auditLogs;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->notifications = new ArrayCollection();
        $this->auditLogs = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUsername(): ?string
    {
        return $this->username;
    }

    public function setUsername(string $username): static
    {
        $this->username = $username;

        return $this;
    }

    public function getUserIdentifier(): string
    {
        return (string) $this->username;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    public function getRoles(): array
    {
        $roles = $this->roles;
        // Guarantee every user has at least ROLE_USER
        $roles[] = 'ROLE_USER';

        return array_unique($roles);
    }

    /**
     * @param list<string> $roles
     */
    public function setRoles(array $roles): static
    {
        $this->roles = $roles;

        return $this;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;

        return $this;
    }

    public function eraseCredentials(): void
    {
        // If you store any temporary, sensitive data on the user, clear it here
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;

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

    public function getStudentProfile(): ?StudentProfile
    {
        return $this->studentProfile;
    }

    public function setStudentProfile(?StudentProfile $studentProfile): static
    {
        // unset the owning side of the relation if necessary
        if ($studentProfile === null && $this->studentProfile !== null) {
            $this->studentProfile->setUser(null);
        }

        // set the owning side of the relation if necessary
        if ($studentProfile !== null && $studentProfile->getUser() !== $this) {
            $studentProfile->setUser($this);
        }

        $this->studentProfile = $studentProfile;

        return $this;
    }

    public function getTeacherProfile(): ?TeacherProfile
    {
        return $this->teacherProfile;
    }

    public function setTeacherProfile(?TeacherProfile $teacherProfile): static
    {
        if ($teacherProfile === null && $this->teacherProfile !== null) {
            $this->teacherProfile->setUser(null);
        }

        if ($teacherProfile !== null && $teacherProfile->getUser() !== $this) {
            $teacherProfile->setUser($this);
        }

        $this->teacherProfile = $teacherProfile;

        return $this;
    }

    /**
     * @return Collection<int, Notification>
     */
    public function getNotifications(): Collection
    {
        return $this->notifications;
    }

    public function addNotification(Notification $notification): static
    {
        if (!$this->notifications->contains($notification)) {
            $this->notifications->add($notification);
            $notification->setUser($this);
        }

        return $this;
    }

    public function removeNotification(Notification $notification): static
    {
        if ($this->notifications->removeElement($notification)) {
            // set the owning side to null (unless already changed)
            if ($notification->getUser() === $this) {
                $notification->setUser(null);
            }
        }

        return $this;
    }

    public function getDisplayName(): string
    {
        if ($this->studentProfile) {
            return $this->studentProfile->getFullName();
        }
        if ($this->teacherProfile) {
            return $this->teacherProfile->getFullName();
        }
        return $this->username ?? 'User';
    }

    public function isStudent(): bool
    {
        return in_array('ROLE_STUDENT', $this->getRoles(), true);
    }

    public function isTeacher(): bool
    {
        return in_array('ROLE_TEACHER', $this->getRoles(), true);
    }

    public function isAdmin(): bool
    {
        return in_array('ROLE_ADMIN', $this->getRoles(), true);
    }
}
