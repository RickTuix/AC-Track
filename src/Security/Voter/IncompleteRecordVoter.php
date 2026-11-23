<?php

namespace App\Security\Voter;

use App\Entity\IncompleteRecord;
use App\Entity\User;
use App\Enum\IncompleteStatus;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class IncompleteRecordVoter extends Voter
{
    public const VIEW = 'INC_VIEW';
    public const EDIT = 'INC_EDIT';
    public const SUBMIT = 'INC_SUBMIT';
    public const RESOLVE = 'INC_RESOLVE';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::VIEW, self::EDIT, self::SUBMIT, self::RESOLVE], true)
            && $subject instanceof IncompleteRecord;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        /** @var IncompleteRecord $record */
        $record = $subject;

        // Admin has full access
        if ($user->isAdmin()) {
            return true;
        }

        return match ($attribute) {
            self::VIEW => $this->canView($record, $user),
            self::EDIT => $this->canEdit($record, $user),
            self::SUBMIT => $this->canSubmit($record, $user),
            self::RESOLVE => $this->canResolve($record, $user),
            default => false,
        };
    }

    private function canView(IncompleteRecord $record, User $user): bool
    {
        // Student can view only their own record
        if ($user->isStudent()) {
            return $record->getStudent()?->getUser()?->getId() === $user->getId();
        }

        // Teacher can view if assigned to the teacher or subject/section
        if ($user->isTeacher()) {
            return $this->isTeacherAssigned($record, $user);
        }

        return false;
    }

    private function canEdit(IncompleteRecord $record, User $user): bool
    {
        // Students cannot edit the definition of an INC
        if ($user->isStudent()) {
            return false;
        }

        if ($user->isTeacher()) {
            return $this->isTeacherAssigned($record, $user);
        }

        return false;
    }

    private function canSubmit(IncompleteRecord $record, User $user): bool
    {
        // Only Pending records can be submitted
        if ($record->getStatus() !== IncompleteStatus::PENDING) {
            return false;
        }

        if ($user->isStudent()) {
            return $record->getStudent()?->getUser()?->getId() === $user->getId();
        }

        // Teacher or Admin can submit on behalf of student
        if ($user->isTeacher()) {
            return $this->isTeacherAssigned($record, $user);
        }

        return false;
    }

    private function canResolve(IncompleteRecord $record, User $user): bool
    {
        // Students can never mark an INC as completed/resolved
        if ($user->isStudent()) {
            return false;
        }

        // Teacher can resolve/review if assigned
        if ($user->isTeacher()) {
            return $this->isTeacherAssigned($record, $user);
        }

        return false;
    }

    private function isTeacherAssigned(IncompleteRecord $record, User $user): bool
    {
        $teacherProfile = $user->getTeacherProfile();
        if (!$teacherProfile) {
            return false;
        }

        // Direct assignment on record
        if ($record->getTeacher()?->getId() === $teacherProfile->getId()) {
            return true;
        }

        // Check if teacher is assigned to the Section + Subject
        $section = $record->getSection();
        $subject = $record->getSubject();
        if ($section && $subject) {
            foreach ($teacherProfile->getSectionSubjects() as $ss) {
                if ($ss->getSection()?->getId() === $section->getId() && $ss->getSubject()?->getId() === $subject->getId()) {
                    return true;
                }
            }
        }

        return false;
    }
}
