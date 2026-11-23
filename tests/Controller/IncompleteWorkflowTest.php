<?php

namespace App\Tests\Controller;

use App\Entity\IncompleteRecord;
use App\Entity\Notification;
use App\Enum\IncompleteStatus;
use App\Repository\AuditLogRepository;
use App\Repository\IncompleteRecordRepository;
use App\Repository\NotificationRepository;
use App\Repository\UserRepository;
use App\Tests\TestHelperTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class IncompleteWorkflowTest extends WebTestCase
{
    use TestHelperTrait;

    private $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = static::createClient();
        $this->reloadFixtures();
    }

    public function testStudentCanSubmitPendingResolution(): void
    {
        $client = $this->client;
        $userRepo = static::getContainer()->get(UserRepository::class);
        $incRepo = static::getContainer()->get(IncompleteRecordRepository::class);

        // student01 has pending INC for IT201
        $studentUser = $userRepo->findOneBy(['username' => 'student01']);
        $inc = $incRepo->findOneBy([
            'student' => $studentUser->getStudentProfile(),
            'status' => IncompleteStatus::PENDING,
        ]);

        $this->assertNotNull($inc);

        $client->loginUser($studentUser);
        $crawler = $client->request('GET', '/student/incompletes/' . $inc->getId());

        $form = $crawler->selectButton('Submit for Teacher Review')->form();
        $form['submission_note'] = 'Here is my completed activity for the midterm review.';

        $client->submit($form);

        $this->assertResponseRedirects('/student/incompletes/' . $inc->getId());

        // Refresh entity from DB
        static::getContainer()->get('doctrine')->getManager()->clear();
        $updatedInc = static::getContainer()->get(IncompleteRecordRepository::class)->find($inc->getId());

        $this->assertSame(IncompleteStatus::SUBMITTED, $updatedInc->getStatus());
        $this->assertNotNull($updatedInc->getSubmittedAt());
        $this->assertSame('Here is my completed activity for the midterm review.', $updatedInc->getSubmissionNote());

        // Verify notification created for student confirmation
        $notifRepo = static::getContainer()->get(NotificationRepository::class);
        $notif = $notifRepo->findOneBy([
            'user' => $studentUser,
            'type' => 'SUBMISSION_CONFIRMATION',
        ]);
        $this->assertNotNull($notif);
    }

    public function testTeacherCanResolveAndCompleteSubmittedRequirement(): void
    {
        $client = $this->client;
        $userRepo = static::getContainer()->get(UserRepository::class);
        $incRepo = static::getContainer()->get(IncompleteRecordRepository::class);

        // Find a SUBMITTED record
        $submittedInc = $incRepo->findOneBy(['status' => IncompleteStatus::SUBMITTED]);
        $this->assertNotNull($submittedInc);

        $teacherUser = $submittedInc->getTeacher()->getUser();

        $client->loginUser($teacherUser);
        $crawler = $client->request('GET', '/teacher/incompletes/' . $submittedInc->getId());

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', $submittedInc->getMissingItemTitle());

        // Teacher submits decision to COMPLETE
        $form = $crawler->selectButton('Mark as Completed / Resolved')->form([
            'teacher_remarks' => 'Work verified and approved with satisfactory score.',
        ]);

        $client->submit($form, ['decision' => 'complete']);

        $this->assertResponseRedirects('/teacher/incompletes/' . $submittedInc->getId());

        static::getContainer()->get('doctrine')->getManager()->clear();
        $resolvedInc = static::getContainer()->get(IncompleteRecordRepository::class)->find($submittedInc->getId());

        $this->assertSame(IncompleteStatus::COMPLETED, $resolvedInc->getStatus());
        $this->assertNotNull($resolvedInc->getResolvedAt());

        // Verify audit log
        $auditRepo = static::getContainer()->get(AuditLogRepository::class);
        $audit = $auditRepo->findOneBy([
            'action' => 'INC_STATUS_CHANGE',
            'entityId' => $resolvedInc->getId(),
        ]);
        $this->assertNotNull($audit);
    }

    public function testTeacherCanReturnSubmissionForRevisions(): void
    {
        $client = $this->client;
        $userRepo = static::getContainer()->get(UserRepository::class);
        $incRepo = static::getContainer()->get(IncompleteRecordRepository::class);

        // Find another SUBMITTED record or create one
        $submittedInc = $incRepo->findOneBy(['status' => IncompleteStatus::SUBMITTED]);
        $this->assertNotNull($submittedInc);

        $teacherUser = $submittedInc->getTeacher()->getUser();
        $client->loginUser($teacherUser);

        $crawler = $client->request('GET', '/teacher/incompletes/' . $submittedInc->getId());

        $form = $crawler->selectButton('Return for Revisions (Set to Pending)')->form([
            'teacher_remarks' => 'Missing code unit tests. Please re-run test suite and resubmit.',
        ]);

        $client->submit($form, ['decision' => 'return']);

        $this->assertResponseRedirects('/teacher/incompletes/' . $submittedInc->getId());

        static::getContainer()->get('doctrine')->getManager()->clear();
        $returnedInc = static::getContainer()->get(IncompleteRecordRepository::class)->find($submittedInc->getId());

        $this->assertSame(IncompleteStatus::PENDING, $returnedInc->getStatus());
        $this->assertStringContainsString('Missing code unit tests', $returnedInc->getTeacherExplanation());
    }
}
