<?php

namespace App\Controller;

use App\Entity\GradingPeriod;
use App\Entity\IncompleteRecord;
use App\Entity\Section;
use App\Entity\SectionSubject;
use App\Entity\StudentProfile;
use App\Entity\Subject;
use App\Entity\TeacherProfile;
use App\Entity\User;
use App\Enum\IncompleteStatus;
use App\Repository\GradeRepository;
use App\Repository\GradingPeriodRepository;
use App\Repository\IncompleteRecordRepository;
use App\Repository\SectionRepository;
use App\Repository\SectionSubjectRepository;
use App\Repository\StudentProfileRepository;
use App\Repository\SubjectRepository;
use App\Security\Voter\IncompleteRecordVoter;
use App\Service\GradeService;
use App\Service\IncompleteService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/teacher')]
#[IsGranted('ROLE_TEACHER')]
class TeacherController extends AbstractController
{
    private function getTeacherProfile(): TeacherProfile
    {
        /** @var User $user */
        $user = $this->getUser();
        $profile = $user->getTeacherProfile();

        if (!$profile) {
            throw $this->createNotFoundException('Teacher profile not found for the current user.');
        }

        return $profile;
    }

    private function assertTeachesSectionSubject(Section $section, Subject $subject): void
    {
        /** @var User $user */
        $user = $this->getUser();
        if ($user->isAdmin()) {
            return;
        }

        $teacher = $this->getTeacherProfile();
        $assigned = false;
        foreach ($teacher->getSectionSubjects() as $ss) {
            if ($ss->getSection()?->getId() === $section->getId() && $ss->getSubject()?->getId() === $subject->getId()) {
                $assigned = true;
                break;
            }
        }

        if (!$assigned) {
            throw $this->createAccessDeniedException('You are not assigned to instruct this subject for this section.');
        }
    }

    #[Route('/dashboard', name: 'teacher_dashboard')]
    public function dashboard(
        SectionSubjectRepository $ssRepo,
        IncompleteRecordRepository $incRepo
    ): Response {
        $teacher = $this->getTeacherProfile();
        $assignments = $ssRepo->findByTeacher($teacher);

        $allIncs = $incRepo->findByTeacher($teacher);
        $pendingCount = count(array_filter($allIncs, fn($i) => $i->getStatus() === IncompleteStatus::PENDING));
        $submittedCount = count(array_filter($allIncs, fn($i) => $i->getStatus() === IncompleteStatus::SUBMITTED));
        $completedCount = count(array_filter($allIncs, fn($i) => $i->getStatus() === IncompleteStatus::COMPLETED));

        // Submissions awaiting review
        $awaitingReview = array_filter($allIncs, fn($i) => $i->getStatus() === IncompleteStatus::SUBMITTED);
        usort($awaitingReview, fn($a, $b) => $b->getSubmittedAt() <=> $a->getSubmittedAt());

        return $this->render('teacher/dashboard.html.twig', [
            'teacher' => $teacher,
            'assignments' => $assignments,
            'pendingCount' => $pendingCount,
            'submittedCount' => $submittedCount,
            'completedCount' => $completedCount,
            'awaitingReview' => array_slice($awaitingReview, 0, 5),
        ]);
    }

    #[Route('/sections/{sectionId}/subjects/{subjectId}/grades', name: 'teacher_grades_matrix', requirements: ['sectionId' => '\d+', 'subjectId' => '\d+'])]
    public function gradesMatrix(
        int $sectionId,
        int $subjectId,
        Request $request,
        SectionRepository $sectionRepo,
        SubjectRepository $subjectRepo,
        GradingPeriodRepository $periodRepo,
        GradeRepository $gradeRepo,
        GradeService $gradeService
    ): Response {
        $section = $sectionRepo->find($sectionId);
        $subject = $subjectRepo->find($subjectId);

        if (!$section || !$subject) {
            throw $this->createNotFoundException('Section or subject not found.');
        }

        $this->assertTeachesSectionSubject($section, $subject);

        $periods = $periodRepo->findOrdered();
        $students = $section->getStudents()->toArray();
        usort($students, fn($a, $b) => strcmp($a->getLastName(), $b->getLastName()));

        // Handle POST grade submissions
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('save_grades', (string) $request->request->get('_token'))) {
                $this->addFlash('danger', 'Invalid security token.');
                return $this->redirectToRoute('teacher_grades_matrix', ['sectionId' => $sectionId, 'subjectId' => $subjectId]);
            }

            /** @var User $currentUser */
            $currentUser = $this->getUser();
            $gradesData = $request->request->all('grades');
            $updated = 0;

            foreach ($gradesData as $studentId => $periodsGrades) {
                $student = null;
                foreach ($students as $st) {
                    if ($st->getId() === (int) $studentId) {
                        $student = $st;
                        break;
                    }
                }
                if (!$student) continue;

                foreach ($periodsGrades as $periodId => $data) {
                    $period = null;
                    foreach ($periods as $p) {
                        if ($p->getId() === (int) $periodId) {
                            $period = $p;
                            break;
                        }
                    }
                    if (!$period) continue;

                    $val = isset($data['val']) && trim((string)$data['val']) !== '' ? trim((string)$data['val']) : null;
                    $isInc = !empty($data['is_inc']);
                    $remarks = !empty($data['remarks']) ? trim((string)$data['remarks']) : null;

                    if ($val !== null || $isInc) {
                        $gradeService->recordGrade(
                            $student,
                            $subject,
                            $section,
                            $period,
                            $val,
                            $isInc,
                            $remarks,
                            $currentUser
                        );
                        $updated++;
                    }
                }
            }

            $this->addFlash('success', sprintf('Successfully recorded/updated %d grade entries.', $updated));
            return $this->redirectToRoute('teacher_grades_matrix', ['sectionId' => $sectionId, 'subjectId' => $subjectId]);
        }

        // Fetch existing grades
        $existingGrades = $gradeRepo->findBySectionAndSubject($section, $subject);
        $gradeMatrix = [];
        foreach ($existingGrades as $g) {
            $gradeMatrix[$g->getStudent()->getId()][$g->getGradingPeriod()->getId()] = $g;
        }

        return $this->render('teacher/grades_matrix.html.twig', [
            'section' => $section,
            'subject' => $subject,
            'students' => $students,
            'periods' => $periods,
            'gradeMatrix' => $gradeMatrix,
        ]);
    }

    #[Route('/incompletes', name: 'teacher_incompletes')]
    public function incompletes(
        Request $request,
        IncompleteRecordRepository $incRepo,
        SectionSubjectRepository $ssRepo
    ): Response {
        $teacher = $this->getTeacherProfile();
        $statusParam = $request->query->get('status');
        $subjectParam = $request->query->get('subject');
        $statusEnum = $statusParam ? IncompleteStatus::tryFrom($statusParam) : null;

        $incompletes = $incRepo->findByTeacher($teacher, $statusEnum);

        if ($subjectParam) {
            $incompletes = array_filter($incompletes, fn($i) => $i->getSubject()->getCode() === $subjectParam);
        }

        $assignments = $ssRepo->findByTeacher($teacher);

        return $this->render('teacher/incompletes.html.twig', [
            'teacher' => $teacher,
            'incompletes' => $incompletes,
            'currentStatus' => $statusParam,
            'currentSubject' => $subjectParam,
            'assignments' => $assignments,
        ]);
    }

    #[Route('/incompletes/new', name: 'teacher_incomplete_new')]
    public function newIncomplete(
        Request $request,
        SectionSubjectRepository $ssRepo,
        GradingPeriodRepository $periodRepo,
        StudentProfileRepository $studentRepo,
        SectionRepository $sectionRepo,
        SubjectRepository $subjectRepo,
        IncompleteService $incService
    ): Response {
        $teacher = $this->getTeacherProfile();
        $assignments = $ssRepo->findByTeacher($teacher);
        $periods = $periodRepo->findOrdered();

        if (empty($assignments)) {
            $this->addFlash('warning', 'You have no assigned subjects or sections to issue INC records for.');
            return $this->redirectToRoute('teacher_dashboard');
        }

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('create_inc', (string) $request->request->get('_token'))) {
                $this->addFlash('danger', 'Invalid security token.');
                return $this->redirectToRoute('teacher_incomplete_new');
            }

            $studentId = (int) $request->request->get('student_id');
            $assignmentId = (int) $request->request->get('assignment_id');
            $periodId = (int) $request->request->get('period_id');
            $category = (string) $request->request->get('missing_category');
            $title = trim((string) $request->request->get('missing_item_title'));
            $explanation = trim((string) $request->request->get('teacher_explanation'));
            $requiredAction = trim((string) $request->request->get('required_action'));
            $deadlineStr = (string) $request->request->get('deadline');

            $student = $studentRepo->find($studentId);
            $assignment = $ssRepo->find($assignmentId);
            $period = $periodRepo->find($periodId);

            if (!$student || !$assignment || !$period || empty($title) || empty($explanation) || empty($requiredAction) || empty($deadlineStr)) {
                $this->addFlash('danger', 'All fields are required to create an Incomplete Record.');
                return $this->redirectToRoute('teacher_incomplete_new');
            }

            // Voter security check: ensure assignment belongs to teacher
            if ($assignment->getTeacher()?->getId() !== $teacher->getId()) {
                throw $this->createAccessDeniedException('You can only issue INCs for subjects assigned to you.');
            }

            $deadline = new \DateTime($deadlineStr);

            $record = new IncompleteRecord();
            $record->setStudent($student)
                ->setSubject($assignment->getSubject())
                ->setSection($assignment->getSection())
                ->setGradingPeriod($period)
                ->setTeacher($teacher)
                ->setMissingCategory($category)
                ->setMissingItemTitle($title)
                ->setTeacherExplanation($explanation)
                ->setRequiredAction($requiredAction)
                ->setDeadline($deadline);

            /** @var User $currentUser */
            $currentUser = $this->getUser();
            $incService->createRecord($record, $currentUser);

            $this->addFlash('success', sprintf('INC record issued for %s in %s.', $student->getFullName(), $assignment->getSubject()->getCode()));
            return $this->redirectToRoute('teacher_incompletes');
        }

        return $this->render('teacher/incomplete_new.html.twig', [
            'teacher' => $teacher,
            'assignments' => $assignments,
            'periods' => $periods,
        ]);
    }

    #[Route('/incompletes/{id}', name: 'teacher_incomplete_detail', requirements: ['id' => '\d+'])]
    public function detail(IncompleteRecord $record): Response
    {
        $this->denyAccessUnlessGranted(IncompleteRecordVoter::VIEW, $record);

        return $this->render('teacher/incomplete_detail.html.twig', [
            'record' => $record,
        ]);
    }

    #[Route('/incompletes/{id}/resolve', name: 'teacher_incomplete_resolve', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function resolve(
        Request $request,
        IncompleteRecord $record,
        IncompleteService $incService
    ): Response {
        $this->denyAccessUnlessGranted(IncompleteRecordVoter::RESOLVE, $record);

        if (!$this->isCsrfTokenValid('resolve_inc_' . $record->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Invalid security token.');
            return $this->redirectToRoute('teacher_incomplete_detail', ['id' => $record->getId()]);
        }

        $action = (string) $request->request->get('decision'); // 'complete' or 'return'
        $remarks = trim((string) $request->request->get('teacher_remarks'));
        /** @var User $currentUser */
        $currentUser = $this->getUser();

        if ($action === 'complete') {
            $incService->markCompleted($record, $remarks, $currentUser);
            $this->addFlash('success', sprintf('Requirement for %s has been marked as COMPLETED / RESOLVED.', $record->getStudent()->getFullName()));
        } elseif ($action === 'return') {
            if (empty($remarks)) {
                $this->addFlash('danger', 'Please provide feedback remarks when returning a submission for revisions.');
                return $this->redirectToRoute('teacher_incomplete_detail', ['id' => $record->getId()]);
            }
            $incService->returnToPending($record, $remarks, $currentUser);
            $this->addFlash('warning', sprintf('Requirement for %s was returned for revisions.', $record->getStudent()->getFullName()));
        }

        return $this->redirectToRoute('teacher_incomplete_detail', ['id' => $record->getId()]);
    }

    #[Route('/incompletes/{id}/edit', name: 'teacher_incomplete_edit', requirements: ['id' => '\d+'])]
    public function edit(
        Request $request,
        IncompleteRecord $record,
        IncompleteService $incService
    ): Response {
        $this->denyAccessUnlessGranted(IncompleteRecordVoter::EDIT, $record);

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('edit_inc_' . $record->getId(), (string) $request->request->get('_token'))) {
                $this->addFlash('danger', 'Invalid security token.');
                return $this->redirectToRoute('teacher_incomplete_edit', ['id' => $record->getId()]);
            }

            $oldValues = [
                'category' => $record->getMissingCategory(),
                'title' => $record->getMissingItemTitle(),
                'explanation' => $record->getTeacherExplanation(),
                'requiredAction' => $record->getRequiredAction(),
                'deadline' => $record->getDeadline()?->format('Y-m-d H:i:s'),
                'status' => $record->getStatus()->value,
            ];

            $category = (string) $request->request->get('missing_category');
            $title = trim((string) $request->request->get('missing_item_title'));
            $explanation = trim((string) $request->request->get('teacher_explanation'));
            $requiredAction = trim((string) $request->request->get('required_action'));
            $deadlineStr = (string) $request->request->get('deadline');

            $record->setMissingCategory($category)
                ->setMissingItemTitle($title)
                ->setTeacherExplanation($explanation)
                ->setRequiredAction($requiredAction)
                ->setDeadline(new \DateTime($deadlineStr));

            /** @var User $currentUser */
            $currentUser = $this->getUser();
            $incService->updateRecord($record, $oldValues, $currentUser);

            $this->addFlash('success', 'INC requirement details updated successfully.');
            return $this->redirectToRoute('teacher_incomplete_detail', ['id' => $record->getId()]);
        }

        return $this->render('teacher/incomplete_edit.html.twig', [
            'record' => $record,
        ]);
    }

    #[Route('/incompletes/{id}/download', name: 'teacher_incomplete_download', requirements: ['id' => '\d+'])]
    public function downloadAttachment(IncompleteRecord $record, string $uploadDir): Response
    {
        $this->denyAccessUnlessGranted(IncompleteRecordVoter::VIEW, $record);

        if (!$record->getAttachmentPath()) {
            throw $this->createNotFoundException('No attached file for this requirement.');
        }

        $filePath = $uploadDir . '/' . $record->getAttachmentPath();
        if (!file_exists($filePath)) {
            throw $this->createNotFoundException('Attached file not found on disk.');
        }

        $response = new BinaryFileResponse($filePath);
        $response->setContentDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            $record->getAttachmentOriginalName() ?: $record->getAttachmentPath()
        );

        return $response;
    }
}
