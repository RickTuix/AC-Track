<?php

namespace App\Security\Voter;

use App\Entity\SectionSubject;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class SectionSubjectVoter extends Voter
{
    public const MANAGE = 'SECTION_SUBJECT_MANAGE';
    public const VIEW = 'SECTION_SUBJECT_VIEW';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::MANAGE, self::VIEW], true)
            && $subject instanceof SectionSubject;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        /** @var SectionSubject $ss */
        $ss = $subject;

        if ($user->isAdmin()) {
            return true;
        }

        $teacher = $user->getTeacherProfile();
        if ($user->isTeacher() && $teacher) {
            return $ss->getTeacher()?->getId() === $teacher->getId();
        }

        if ($user->isStudent() && $attribute === self::VIEW) {
            $student = $user->getStudentProfile();
            return $student && $student->getSection()?->getId() === $ss->getSection()?->getId();
        }

        return false;
    }
}
