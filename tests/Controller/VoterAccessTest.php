<?php

namespace App\Tests\Controller;

use App\Entity\Section;
use App\Entity\Subject;
use App\Repository\IncompleteRecordRepository;
use App\Repository\SectionRepository;
use App\Repository\SubjectRepository;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class VoterAccessTest extends WebTestCase
{
    public function testStudentCannotViewAnotherStudentsIncompleteRecord(): void
    {
        $client = static::createClient();
        $userRepo = static::getContainer()->get(UserRepository::class);
        $incRepo = static::getContainer()->get(IncompleteRecordRepository::class);

        // student01 (Carl Alvarez)
        $student1User = $userRepo->findOneBy(['username' => 'student01']);
        // Find an INC that belongs to student03 or student04 (not student01)
        $otherStudentUser = $userRepo->findOneBy(['username' => 'student04']);
        $otherInc = $incRepo->findOneBy(['student' => $otherStudentUser->getStudentProfile()]);

        $this->assertNotNull($otherInc, 'Test fixture must have an INC for another student');

        // Login as student01 and try to access other student's INC
        $client->loginUser($student1User);
        $client->request('GET', '/student/incompletes/' . $otherInc->getId());

        // Must be Access Denied (HTTP 403)
        $this->assertResponseStatusCodeSame(403);
    }

    public function testStudentCanViewTheirOwnIncompleteRecord(): void
    {
        $client = static::createClient();
        $userRepo = static::getContainer()->get(UserRepository::class);
        $incRepo = static::getContainer()->get(IncompleteRecordRepository::class);

        $student1User = $userRepo->findOneBy(['username' => 'student01']);
        $ownInc = $incRepo->findOneBy(['student' => $student1User->getStudentProfile()]);

        $this->assertNotNull($ownInc, 'Test fixture must have an INC for student01');

        $client->loginUser($student1User);
        $client->request('GET', '/student/incompletes/' . $ownInc->getId());

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', $ownInc->getMissingItemTitle());
    }

    public function testTeacherCannotManageUnassignedSubjectAndSection(): void
    {
        $client = static::createClient();
        $userRepo = static::getContainer()->get(UserRepository::class);
        $sectionRepo = static::getContainer()->get(SectionRepository::class);
        $subjectRepo = static::getContainer()->get(SubjectRepository::class);

        // Teacher Santos is assigned to CS101 and CS102 for BSCS-3A
        // Teacher Santos is NOT assigned to IT201 for BSIT-3A (taught by Teacher Reyes)
        $santos = $userRepo->findOneBy(['username' => 'teacher.santos']);
        $sectionIT = $sectionRepo->findOneBy(['name' => 'BSIT-3A']);
        $subjectIT201 = $subjectRepo->findOneBy(['code' => 'IT201']);

        $client->loginUser($santos);
        $client->request('GET', sprintf('/teacher/sections/%d/subjects/%d/grades', $sectionIT->getId(), $subjectIT201->getId()));

        // Must be Access Denied (HTTP 403)
        $this->assertResponseStatusCodeSame(403);
    }

    public function testTeacherCanManageAssignedSubjectAndSection(): void
    {
        $client = static::createClient();
        $userRepo = static::getContainer()->get(UserRepository::class);
        $sectionRepo = static::getContainer()->get(SectionRepository::class);
        $subjectRepo = static::getContainer()->get(SubjectRepository::class);

        // Teacher Santos is assigned to CS101 for BSCS-3A
        $santos = $userRepo->findOneBy(['username' => 'teacher.santos']);
        $sectionCS = $sectionRepo->findOneBy(['name' => 'BSCS-3A']);
        $subjectCS101 = $subjectRepo->findOneBy(['code' => 'CS101']);

        $client->loginUser($santos);
        $client->request('GET', sprintf('/teacher/sections/%d/subjects/%d/grades', $sectionCS->getId(), $subjectCS101->getId()));

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', $subjectCS101->getTitle());
    }

    public function testStudentCannotAccessTeacherOrAdminPortals(): void
    {
        $client = static::createClient();
        $userRepo = static::getContainer()->get(UserRepository::class);
        $student = $userRepo->findOneBy(['username' => 'student01']);

        $client->loginUser($student);

        $client->request('GET', '/teacher/dashboard');
        $this->assertResponseStatusCodeSame(403);

        $client->request('GET', '/admin/dashboard');
        $this->assertResponseStatusCodeSame(403);
    }
}
