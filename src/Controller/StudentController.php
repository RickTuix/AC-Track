<?php

namespace App\Controller;

use App\Entity\IncompleteRecord;
use App\Entity\Notification;
use App\Entity\StudentProfile;
use App\Entity\User;
use App\Enum\IncompleteStatus;
use App\Repository\GradeRepository;
use App\Repository\GradingPeriodRepository;
use App\Repository\IncompleteRecordRepository;
use App\Repository\NotificationRepository;
use App\Security\Voter\IncompleteRecordVoter;
use App\Service\IncompleteService;
use Dompdf\Dompdf;
use Dompdf\Options;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/student')]
#[IsGranted('ROLE_STUDENT')]
class StudentController extends AbstractController
{
    private function getStudentProfile(): StudentProfile
    {
        /** @var User $user */
        $user = $this->getUser();
        $profile = $user->getStudentProfile();

        if (!$profile) {
            throw $this->createNotFoundException('Student profile not found for the current user.');
        }

        return $profile;
    }

    #[Route('/dashboard', name: 'student_dashboard')]
    public function dashboard(
        GradeRepository $gradeRepo,
        IncompleteRecordRepository $incRepo,
        NotificationRepository $notifRepo
    ): Response {
        $student = $this->getStudentProfile();
        $grades = $gradeRepo->findByStudent($student);
        $allIncs = $incRepo->findByStudent($student);

        $pendingIncs = array_filter($allIncs, fn($i) => $i->getStatus() === IncompleteStatus::PENDING);
        $submittedIncs = array_filter($allIncs, fn($i) => $i->getStatus() === IncompleteStatus::SUBMITTED);
        $completedIncs = array_filter($allIncs, fn($i) => $i->getStatus() === IncompleteStatus::COMPLETED);

        // Nearest deadlines among pending
        $upcomingDeadlines = $pendingIncs;
        usort($upcomingDeadlines, fn($a, $b) => $a->getDeadline() <=> $b->getDeadline());
        $upcomingDeadlines = array_slice($upcomingDeadlines, 0, 5);

        // Calculate simple descriptive standing: average of recorded numeric grades
        $numericGrades = array_filter($grades, fn($g) => !$g->isInc() && $g->getNumericalValue() !== null);
        $gpa = 0.0;
        if (count($numericGrades) > 0) {
            $sum = array_sum(array_map(fn($g) => (float) $g->getNumericalValue(), $numericGrades));
            $gpa = round($sum / count($numericGrades), 2);
        }

        /** @var User $user */
        $user = $this->getUser();
        $recentNotifs = $notifRepo->findForUser($user, 5);

        return $this->render('student/dashboard.html.twig', [
            'student' => $student,
            'gradesCount' => count($grades),
            'gpa' => $gpa,
            'pendingCount' => count($pendingIncs),
            'submittedCount' => count($submittedIncs),
            'completedCount' => count($completedIncs),
            'upcomingDeadlines' => $upcomingDeadlines,
            'recentNotifications' => $recentNotifs,
        ]);
    }

    #[Route('/grades', name: 'student_grades')]
    public function grades(
        GradeRepository $gradeRepo,
        GradingPeriodRepository $periodRepo,
        IncompleteRecordRepository $incRepo
    ): Response {
        $student = $this->getStudentProfile();
        $grades = $gradeRepo->findByStudent($student);
        $periods = $periodRepo->findOrdered();
        $allIncs = $incRepo->findByStudent($student);

        // Map grades by subject_id and period_id
        $matrix = [];
        $subjects = [];
        foreach ($grades as $g) {
            $sub = $g->getSubject();
            if ($sub) {
                $subjects[$sub->getId()] = $sub;
                $matrix[$sub->getId()][$g->getGradingPeriod()->getId()] = $g;
            }
        }

        // Map INCs by subject_id and period_id
        $incMap = [];
        foreach ($allIncs as $inc) {
            $incMap[$inc->getSubject()->getId()][$inc->getGradingPeriod()->getId()] = $inc;
        }

        return $this->render('student/grades.html.twig', [
            'student' => $student,
            'subjects' => $subjects,
            'periods' => $periods,
            'matrix' => $matrix,
            'incMap' => $incMap,
        ]);
    }

    #[Route('/incompletes', name: 'student_incompletes')]
    public function incompletes(Request $request, IncompleteRecordRepository $incRepo): Response
    {
        $student = $this->getStudentProfile();
        $statusFilter = $request->query->get('status');
        $statusEnum = $statusFilter ? IncompleteStatus::tryFrom($statusFilter) : null;

        $incompletes = $incRepo->findByStudent($student, $statusEnum);

        return $this->render('student/incompletes.html.twig', [
            'student' => $student,
            'incompletes' => $incompletes,
            'currentFilter' => $statusFilter,
        ]);
    }

    #[Route('/incompletes/{id}', name: 'student_incomplete_detail', requirements: ['id' => '\d+'])]
    public function incompleteDetail(IncompleteRecord $record): Response
    {
        $this->denyAccessUnlessGranted(IncompleteRecordVoter::VIEW, $record);

        return $this->render('student/incomplete_detail.html.twig', [
            'record' => $record,
        ]);
    }

    #[Route('/incompletes/{id}/submit', name: 'student_incomplete_submit', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function submitResolution(
        Request $request,
        IncompleteRecord $record,
        IncompleteService $incompleteService
    ): Response {
        $this->denyAccessUnlessGranted(IncompleteRecordVoter::SUBMIT, $record);

        if (!$this->isCsrfTokenValid('submit_inc_' . $record->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Invalid security token. Please try again.');
            return $this->redirectToRoute('student_incomplete_detail', ['id' => $record->getId()]);
        }

        $note = trim((string) $request->request->get('submission_note'));
        /** @var UploadedFile|null $file */
        $file = $request->files->get('attachment');

        if ($file) {
            // Validate file size (max 10MB)
            if ($file->getSize() > 10 * 1024 * 1024) {
                $this->addFlash('danger', 'The uploaded file exceeds the 10MB limit.');
                return $this->redirectToRoute('student_incomplete_detail', ['id' => $record->getId()]);
            }

            // Validate extension / mime
            $allowedExtensions = ['pdf', 'doc', 'docx', 'zip', 'png', 'jpg', 'jpeg', 'txt'];
            $ext = strtolower((string) $file->getClientOriginalExtension());
            if (!in_array($ext, $allowedExtensions, true)) {
                $this->addFlash('danger', 'Invalid file type. Allowed formats: PDF, DOC, DOCX, ZIP, PNG, JPG, TXT.');
                return $this->redirectToRoute('student_incomplete_detail', ['id' => $record->getId()]);
            }
        }

        try {
            /** @var User $currentUser */
            $currentUser = $this->getUser();
            $incompleteService->submitResolution($record, $note, $file, $currentUser);
            $this->addFlash('success', 'Your resolution was successfully submitted for teacher review.');
        } catch (\Exception $e) {
            $this->addFlash('danger', 'Error submitting requirement: ' . $e->getMessage());
        }

        return $this->redirectToRoute('student_incomplete_detail', ['id' => $record->getId()]);
    }

    #[Route('/incompletes/{id}/download', name: 'student_incomplete_download', requirements: ['id' => '\d+'])]
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

    #[Route('/reports/grades', name: 'student_grade_report')]
    public function gradeReport(
        Request $request,
        GradeRepository $gradeRepo,
        GradingPeriodRepository $periodRepo,
        IncompleteRecordRepository $incRepo
    ): Response {
        $student = $this->getStudentProfile();
        $grades = $gradeRepo->findByStudent($student);
        $periods = $periodRepo->findOrdered();
        $allIncs = $incRepo->findByStudent($student);

        $matrix = [];
        $subjects = [];
        foreach ($grades as $g) {
            $sub = $g->getSubject();
            if ($sub) {
                $subjects[$sub->getId()] = $sub;
                $matrix[$sub->getId()][$g->getGradingPeriod()->getId()] = $g;
            }
        }

        $incMap = [];
        foreach ($allIncs as $inc) {
            $incMap[$inc->getSubject()->getId()][$inc->getGradingPeriod()->getId()] = $inc;
        }

        $exportPdf = $request->query->getBoolean('pdf', false);
        if ($exportPdf) {
            $html = $this->renderView('student/report_pdf.html.twig', [
                'student' => $student,
                'subjects' => $subjects,
                'periods' => $periods,
                'matrix' => $matrix,
                'incMap' => $incMap,
                'generatedAt' => new \DateTime(),
            ]);

            $options = new Options();
            $options->set('isRemoteEnabled', true);
            $options->set('defaultFont', 'Helvetica');
            $dompdf = new Dompdf($options);
            $dompdf->loadHtml($html);
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->render();

            return new Response(
                $dompdf->output(),
                Response::HTTP_OK,
                [
                    'Content-Type' => 'application/pdf',
                    'Content-Disposition' => sprintf('attachment; filename="GradeReport-%s.pdf"', $student->getStudentNumber()),
                ]
            );
        }

        return $this->render('student/report.html.twig', [
            'student' => $student,
            'subjects' => $subjects,
            'periods' => $periods,
            'matrix' => $matrix,
            'incMap' => $incMap,
        ]);
    }
}
