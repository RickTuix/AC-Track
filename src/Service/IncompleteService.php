<?php

namespace App\Service;

use App\Entity\IncompleteRecord;
use App\Entity\User;
use App\Enum\IncompleteStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\String\Slugger\SluggerInterface;

class IncompleteService
{
    public function __construct(
        private EntityManagerInterface $em,
        private NotificationService $notificationService,
        private AuditService $auditService,
        private SluggerInterface $slugger,
        private string $uploadDir
    ) {
    }

    public function createRecord(IncompleteRecord $record, User $currentUser): IncompleteRecord
    {
        $record->setCreatedBy($currentUser);
        $record->setUpdatedBy($currentUser);
        $record->setStatus(IncompleteStatus::PENDING);

        $this->em->persist($record);
        $this->em->flush();

        // Audit Log
        $this->auditService->log(
            'INC_CREATE',
            'IncompleteRecord',
            $record->getId(),
            null,
            [
                'student' => $record->getStudent()?->getFullName(),
                'subject' => $record->getSubject()?->getCode(),
                'period' => $record->getGradingPeriod()?->getName(),
                'category' => $record->getMissingCategory(),
                'title' => $record->getMissingItemTitle(),
                'deadline' => $record->getDeadline()?->format('Y-m-d H:i:s'),
                'status' => $record->getStatus()->value,
            ],
            $currentUser
        );

        // Notify Student
        $studentUser = $record->getStudent()?->getUser();
        if ($studentUser) {
            $this->notificationService->notify(
                $studentUser,
                sprintf('New Incomplete Requirement: %s', $record->getSubject()?->getCode()),
                sprintf(
                    'An INC requirement has been logged for %s (%s). Missing: %s. Deadline: %s.',
                    $record->getSubject()?->getTitle(),
                    $record->getGradingPeriod()?->getName(),
                    $record->getMissingItemTitle(),
                    $record->getDeadline()?->format('M d, Y h:i A')
                ),
                sprintf('/student/incompletes/%d', $record->getId()),
                'INC_CREATED'
            );
        }

        return $record;
    }

    public function updateRecord(IncompleteRecord $record, array $oldValues, User $currentUser): IncompleteRecord
    {
        $record->setUpdatedBy($currentUser);
        $record->setUpdatedAt(new \DateTimeImmutable());
        $this->em->flush();

        $newValues = [
            'category' => $record->getMissingCategory(),
            'title' => $record->getMissingItemTitle(),
            'explanation' => $record->getTeacherExplanation(),
            'requiredAction' => $record->getRequiredAction(),
            'deadline' => $record->getDeadline()?->format('Y-m-d H:i:s'),
            'status' => $record->getStatus()->value,
        ];

        // Audit Log
        $this->auditService->log(
            'INC_EDIT',
            'IncompleteRecord',
            $record->getId(),
            $oldValues,
            $newValues,
            $currentUser
        );

        // Check what changed for specific notifications
        $deadlineChanged = isset($oldValues['deadline']) && $oldValues['deadline'] !== $newValues['deadline'];
        $actionChanged = isset($oldValues['requiredAction']) && $oldValues['requiredAction'] !== $newValues['requiredAction'];
        $explanationChanged = isset($oldValues['explanation']) && $oldValues['explanation'] !== $newValues['explanation'];

        $studentUser = $record->getStudent()?->getUser();
        if ($studentUser && ($deadlineChanged || $actionChanged || $explanationChanged)) {
            $changes = [];
            if ($deadlineChanged) $changes[] = 'deadline';
            if ($actionChanged) $changes[] = 'required instructions';
            if ($explanationChanged) $changes[] = 'teacher remarks';

            $this->notificationService->notify(
                $studentUser,
                sprintf('INC Updated: %s (%s)', $record->getSubject()?->getCode(), implode(', ', $changes)),
                sprintf(
                    'Updates were made to your INC requirement for %s (%s). New Deadline: %s.',
                    $record->getMissingItemTitle(),
                    $record->getSubject()?->getCode(),
                    $record->getDeadline()?->format('M d, Y h:i A')
                ),
                sprintf('/student/incompletes/%d', $record->getId()),
                'INC_EDITED'
            );
        }

        return $record;
    }

    public function submitResolution(IncompleteRecord $record, ?string $submissionNote, ?UploadedFile $uploadedFile, User $currentUser): void
    {
        if ($record->getStatus() !== IncompleteStatus::PENDING) {
            throw new \LogicException('Only pending requirements can be submitted.');
        }

        $oldStatus = $record->getStatus()->value;

        if ($uploadedFile) {
            $originalFilename = pathinfo($uploadedFile->getClientOriginalName(), PATHINFO_FILENAME);
            $safeFilename = $this->slugger->slug($originalFilename);
            $newFilename = sprintf('%s-%s.%s', $safeFilename, uniqid(), $uploadedFile->guessExtension());

            if (!is_dir($this->uploadDir)) {
                mkdir($this->uploadDir, 0777, true);
            }

            $uploadedFile->move($this->uploadDir, $newFilename);

            $record->setAttachmentPath($newFilename);
            $record->setAttachmentOriginalName($uploadedFile->getClientOriginalName());
        }

        $record->setSubmissionNote($submissionNote);
        $record->setSubmittedAt(new \DateTime());
        $record->setStatus(IncompleteStatus::SUBMITTED);
        $record->setUpdatedBy($currentUser);
        $record->setUpdatedAt(new \DateTimeImmutable());

        $this->em->flush();

        // Audit Log
        $this->auditService->log(
            'INC_STATUS_CHANGE',
            'IncompleteRecord',
            $record->getId(),
            ['status' => $oldStatus],
            [
                'status' => IncompleteStatus::SUBMITTED->value,
                'submittedAt' => $record->getSubmittedAt()?->format('Y-m-d H:i:s'),
                'attachment' => $record->getAttachmentOriginalName(),
                'note' => $submissionNote,
            ],
            $currentUser
        );

        // Notify Student (confirmation)
        $studentUser = $record->getStudent()?->getUser();
        if ($studentUser) {
            $this->notificationService->notify(
                $studentUser,
                sprintf('Submission Received: %s', $record->getMissingItemTitle()),
                sprintf('Your resolution for "%s" has been submitted for instructor review.', $record->getMissingItemTitle()),
                sprintf('/student/incompletes/%d', $record->getId()),
                'SUBMISSION_CONFIRMATION'
            );
        }

        // Notify Teacher
        $teacherUser = $record->getTeacher()?->getUser();
        if ($teacherUser) {
            $this->notificationService->notify(
                $teacherUser,
                sprintf('Student Submission: %s (%s)', $record->getStudent()?->getFullName(), $record->getSubject()?->getCode()),
                sprintf(
                    '%s submitted requirements for "%s". Please review and verify completion.',
                    $record->getStudent()?->getFullName(),
                    $record->getMissingItemTitle()
                ),
                sprintf('/teacher/incompletes/%d', $record->getId()),
                'SUBMISSION_RECEIVED'
            );
        }
    }

    public function markCompleted(IncompleteRecord $record, ?string $teacherRemarks, User $currentUser): void
    {
        $oldStatus = $record->getStatus()->value;
        $record->setStatus(IncompleteStatus::COMPLETED);
        $record->setResolvedAt(new \DateTime());
        $record->setUpdatedBy($currentUser);
        $record->setUpdatedAt(new \DateTimeImmutable());

        if ($teacherRemarks) {
            $record->setTeacherExplanation(
                trim($record->getTeacherExplanation() . "\n\n[Completion Remarks by " . $currentUser->getDisplayName() . "]: " . $teacherRemarks)
            );
        }

        $this->em->flush();

        // Audit Log
        $this->auditService->log(
            'INC_STATUS_CHANGE',
            'IncompleteRecord',
            $record->getId(),
            ['status' => $oldStatus],
            [
                'status' => IncompleteStatus::COMPLETED->value,
                'resolvedAt' => $record->getResolvedAt()?->format('Y-m-d H:i:s'),
                'remarks' => $teacherRemarks,
            ],
            $currentUser
        );

        // Notify Student
        $studentUser = $record->getStudent()?->getUser();
        if ($studentUser) {
            $this->notificationService->notify(
                $studentUser,
                sprintf('INC Completed: %s (%s)', $record->getMissingItemTitle(), $record->getSubject()?->getCode()),
                sprintf(
                    'Congratulations! Your INC requirement for "%s" in %s (%s) has been officially marked as COMPLETED by %s.',
                    $record->getMissingItemTitle(),
                    $record->getSubject()?->getTitle(),
                    $record->getGradingPeriod()?->getName(),
                    $currentUser->getDisplayName()
                ),
                sprintf('/student/incompletes/%d', $record->getId()),
                'INC_COMPLETED'
            );
        }
    }

    public function returnToPending(IncompleteRecord $record, string $returnRemarks, User $currentUser): void
    {
        $oldStatus = $record->getStatus()->value;
        $record->setStatus(IncompleteStatus::PENDING);
        $record->setUpdatedBy($currentUser);
        $record->setUpdatedAt(new \DateTimeImmutable());

        $record->setTeacherExplanation(
            trim($record->getTeacherExplanation() . "\n\n[Returned for Revisions by " . $currentUser->getDisplayName() . " on " . date('M d, Y') . "]: " . $returnRemarks)
        );

        $this->em->flush();

        // Audit Log
        $this->auditService->log(
            'INC_STATUS_CHANGE',
            'IncompleteRecord',
            $record->getId(),
            ['status' => $oldStatus],
            [
                'status' => IncompleteStatus::PENDING->value,
                'returnedReason' => $returnRemarks,
            ],
            $currentUser
        );

        // Notify Student
        $studentUser = $record->getStudent()?->getUser();
        if ($studentUser) {
            $this->notificationService->notify(
                $studentUser,
                sprintf('INC Revision Required: %s', $record->getMissingItemTitle()),
                sprintf(
                    'Your submission for "%s" was returned by %s with comments: "%s". Please address and resubmit.',
                    $record->getMissingItemTitle(),
                    $currentUser->getDisplayName(),
                    $returnRemarks
                ),
                sprintf('/student/incompletes/%d', $record->getId()),
                'INC_RETURNED'
            );
        }
    }
}
