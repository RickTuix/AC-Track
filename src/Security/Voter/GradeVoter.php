<?php

namespace App\Security\Voter;

use App\Entity\Grade;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class GradeVoter extends Voter
{
    public const VIEW = 'GRADE_VIEW';
    public const EDIT = 'GRADE_EDIT';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::VIEW, self::EDIT], true)
            && $subject instanceof Grade;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        /** @var Grade $grade */
        $grade = $subject;

        if ($user->isAdmin()) {
            return true;
        }

        return match ($attribute) {
            self::VIEW => $this->canView($grade, $user),
            self::EDIT => $this->canEdit($grade, $user),
            default => false,
        };
    }

    private function canView(Grade $grade, User $user): bool
    {
        if ($user->isStudent()) {
            return $grade->getStudent()?->getUser()?->getId() === $user->getId();
        }

        if ($user->isTeacher()) {
            return $this->canTeacherManage($grade, $user);
        }

        return false;
    }

    private function canEdit(Grade $grade, User $user): bool
    {
        if ($user->isStudent()) {
            return false;
        }

        if ($user->isTeacher()) {
            return $this->canTeacherManage($grade, $user);
        }

        return false;
    }

    private function canTeacherManage(Grade $grade, User $user): bool
    {
        $teacher = $user->getTeacherProfile();
        if (!$teacher) {
            return false;
        }

        $section = $grade->getSection();
        $subject = $grade->getSubject();

        if (!$section || !$subject) {
            return false;
        }

        foreach ($teacher->getSectionSubjects() as $ss) {
            if ($ss->getSection()?->getId() === $section->getId() && $ss->getSubject()?->getId() === $subject->getId()) {
                return true;
            }
        }

        return false;
    }
}
