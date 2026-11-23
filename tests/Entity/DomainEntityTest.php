<?php

namespace App\Tests\Entity;

use App\Entity\IncompleteRecord;
use App\Entity\StudentProfile;
use App\Entity\TeacherProfile;
use App\Entity\User;
use App\Enum\IncompleteStatus;
use PHPUnit\Framework\TestCase;

class DomainEntityTest extends TestCase
{
    public function testIncompleteStatusEnumMethods(): void
    {
        $pending = IncompleteStatus::PENDING;
        $this->assertSame('Pending', $pending->label());
        $this->assertStringContainsString('bg-warning', $pending->badgeClass());

        $submitted = IncompleteStatus::SUBMITTED;
        $this->assertSame('Submitted for Review', $submitted->label());
        $this->assertStringContainsString('bg-info', $submitted->badgeClass());

        $completed = IncompleteStatus::COMPLETED;
        $this->assertSame('Completed / Resolved', $completed->label());
        $this->assertStringContainsString('bg-success', $completed->badgeClass());
    }

    public function testUserRoleHelpers(): void
    {
        $user = new User();
        $user->setUsername('johndoe');
        $user->setRoles(['ROLE_STUDENT']);

        $this->assertTrue($user->isStudent());
        $this->assertFalse($user->isTeacher());
        $this->assertFalse($user->isAdmin());

        $profile = new StudentProfile();
        $profile->setFirstName('John');
        $profile->setLastName('Doe');
        $user->setStudentProfile($profile);

        $this->assertSame('John Doe', $user->getDisplayName());

        $teacherUser = new User();
        $teacherUser->setRoles(['ROLE_TEACHER']);
        $teacherProfile = new TeacherProfile();
        $teacherProfile->setTitle('Dr.');
        $teacherProfile->setFirstName('Jane');
        $teacherProfile->setLastName('Smith');
        $teacherUser->setTeacherProfile($teacherProfile);

        $this->assertSame('Dr. Jane Smith', $teacherUser->getDisplayName());
    }

    public function testIncompleteRecordDeadlineCalculations(): void
    {
        $record = new IncompleteRecord();
        $record->setStatus(IncompleteStatus::PENDING);

        // Past deadline
        $record->setDeadline((new \DateTime())->modify('-2 days'));
        $this->assertTrue($record->isOverdue());

        // Future deadline in 10 days
        $record->setDeadline((new \DateTime())->modify('+10 days'));
        $this->assertFalse($record->isOverdue());
        $this->assertFalse($record->isApproachingDeadline(48));

        // Approaching deadline within 24 hours
        $record->setDeadline((new \DateTime())->modify('+24 hours'));
        $this->assertFalse($record->isOverdue());
        $this->assertTrue($record->isApproachingDeadline(48));

        // When status is COMPLETED, it is never overdue
        $record->setStatus(IncompleteStatus::COMPLETED);
        $record->setDeadline((new \DateTime())->modify('-5 days'));
        $this->assertFalse($record->isOverdue());
    }
}
