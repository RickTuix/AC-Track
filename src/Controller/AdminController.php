<?php

namespace App\Controller;

use App\Entity\AuditLog;
use App\Entity\GradingPeriod;
use App\Entity\IncompleteRecord;
use App\Entity\Section;
use App\Entity\SectionSubject;
use App\Entity\StudentProfile;
use App\Entity\Subject;
use App\Entity\TeacherProfile;
use App\Entity\User;
use App\Enum\IncompleteStatus;
use App\Repository\AuditLogRepository;
use App\Repository\GradeRepository;
use App\Repository\GradingPeriodRepository;
use App\Repository\IncompleteRecordRepository;
use App\Repository\SectionRepository;
use App\Repository\SectionSubjectRepository;
use App\Repository\StudentProfileRepository;
use App\Repository\SubjectRepository;
use App\Repository\TeacherProfileRepository;
use App\Repository\UserRepository;
use App\Service\AuditService;
use Doctrine\ORM\EntityManagerInterface;
use Dompdf\Dompdf;
use Dompdf\Options;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin')]
#[IsGranted('ROLE_ADMIN')]
class AdminController extends AbstractController
{
    #[Route('/dashboard', name: 'admin_dashboard')]
    public function dashboard(
        UserRepository $userRepo,
        StudentProfileRepository $studentRepo,
        TeacherProfileRepository $teacherRepo,
        SubjectRepository $subjectRepo,
        SectionRepository $sectionRepo,
        IncompleteRecordRepository $incRepo,
        AuditLogRepository $auditRepo
    ): Response {
        $totalUsers = $userRepo->count([]);
        $totalStudents = $studentRepo->count([]);
        $totalTeachers = $teacherRepo->count([]);
        $totalSubjects = $subjectRepo->count([]);
        $totalSections = $sectionRepo->count([]);

        $allIncs = $incRepo->findAll();
        $pendingIncs = count(array_filter($allIncs, fn($i) => $i->getStatus() === IncompleteStatus::PENDING));
        $submittedIncs = count(array_filter($allIncs, fn($i) => $i->getStatus() === IncompleteStatus::SUBMITTED));
        $completedIncs = count(array_filter($allIncs, fn($i) => $i->getStatus() === IncompleteStatus::COMPLETED));

        $recentAudits = $auditRepo->findRecent(8);

        return $this->render('admin/dashboard.html.twig', [
            'totalUsers' => $totalUsers,
            'totalStudents' => $totalStudents,
            'totalTeachers' => $totalTeachers,
            'totalSubjects' => $totalSubjects,
            'totalSections' => $totalSections,
            'pendingIncs' => $pendingIncs,
            'submittedIncs' => $submittedIncs,
            'completedIncs' => $completedIncs,
            'totalIncs' => count($allIncs),
            'recentAudits' => $recentAudits,
        ]);
    }

    // ==========================================
    // USERS CRUD
    // ==========================================
    #[Route('/users', name: 'admin_users')]
    public function users(Request $request, UserRepository $userRepo): Response
    {
        $roleFilter = $request->query->get('role');
        $allUsers = $userRepo->findAll();

        if ($roleFilter) {
            $allUsers = array_filter($allUsers, fn(User $u) => in_array($roleFilter, $u->getRoles(), true));
        }

        return $this->render('admin/users/index.html.twig', [
            'users' => $allUsers,
            'currentRole' => $roleFilter,
        ]);
    }

    #[Route('/users/new', name: 'admin_user_new')]
    public function newUser(
        Request $request,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $hasher,
        SectionRepository $sectionRepo,
        AuditService $auditService
    ): Response {
        $sections = $sectionRepo->findAll();

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('new_user', (string) $request->request->get('_token'))) {
                $this->addFlash('danger', 'Invalid security token.');
                return $this->redirectToRoute('admin_user_new');
            }

            $username = trim((string) $request->request->get('username'));
            $email = trim((string) $request->request->get('email'));
            $password = (string) $request->request->get('password');
            $role = (string) $request->request->get('role'); // ROLE_ADMIN, ROLE_TEACHER, ROLE_STUDENT
            $firstName = trim((string) $request->request->get('first_name'));
            $lastName = trim((string) $request->request->get('last_name'));

            $user = new User();
            $user->setUsername($username)
                ->setEmail($email)
                ->setRoles([$role])
                ->setIsActive(true);
            $user->setPassword($hasher->hashPassword($user, $password));

            $em->persist($user);

            if ($role === 'ROLE_STUDENT') {
                $studentNo = trim((string) $request->request->get('student_number'));
                $yearLevel = (string) $request->request->get('year_level');
                $sectionId = (int) $request->request->get('section_id');
                $section = $sectionId ? $sectionRepo->find($sectionId) : null;

                $profile = new StudentProfile();
                $profile->setUser($user)
                    ->setStudentNumber($studentNo)
                    ->setFirstName($firstName)
                    ->setLastName($lastName)
                    ->setYearLevel($yearLevel ?: '1st Year')
                    ->setSection($section);
                $em->persist($profile);
            } elseif ($role === 'ROLE_TEACHER') {
                $empNo = trim((string) $request->request->get('employee_number'));
                $dept = trim((string) $request->request->get('department'));
                $title = (string) $request->request->get('title');

                $profile = new TeacherProfile();
                $profile->setUser($user)
                    ->setEmployeeNumber($empNo)
                    ->setFirstName($firstName)
                    ->setLastName($lastName)
                    ->setDepartment($dept)
                    ->setTitle($title ?: 'Prof.');
                $em->persist($profile);
            }

            $em->flush();

            $auditService->log('USER_CREATE', 'User', $user->getId(), null, [
                'username' => $user->getUsername(),
                'role' => $role,
            ]);

            $this->addFlash('success', sprintf('User %s successfully created.', $user->getUsername()));
            return $this->redirectToRoute('admin_users');
        }

        return $this->render('admin/users/new.html.twig', [
            'sections' => $sections,
        ]);
    }

    #[Route('/users/{id}/edit', name: 'admin_user_edit', requirements: ['id' => '\d+'])]
    public function editUser(
        User $user,
        Request $request,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $hasher,
        SectionRepository $sectionRepo,
        AuditService $auditService
    ): Response {
        $sections = $sectionRepo->findAll();

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('edit_user_' . $user->getId(), (string) $request->request->get('_token'))) {
                $this->addFlash('danger', 'Invalid security token.');
                return $this->redirectToRoute('admin_user_edit', ['id' => $user->getId()]);
            }

            $email = trim((string) $request->request->get('email'));
            $isActive = (bool) $request->request->get('is_active');
            $newPassword = (string) $request->request->get('password');

            $user->setEmail($email);
            $user->setIsActive($isActive);

            if (!empty($newPassword)) {
                $user->setPassword($hasher->hashPassword($user, $newPassword));
            }

            if ($user->getStudentProfile()) {
                $st = $user->getStudentProfile();
                $st->setFirstName(trim((string) $request->request->get('first_name')))
                   ->setLastName(trim((string) $request->request->get('last_name')))
                   ->setYearLevel((string) $request->request->get('year_level'));
                $secId = (int) $request->request->get('section_id');
                $st->setSection($secId ? $sectionRepo->find($secId) : null);
            } elseif ($user->getTeacherProfile()) {
                $tc = $user->getTeacherProfile();
                $tc->setFirstName(trim((string) $request->request->get('first_name')))
                   ->setLastName(trim((string) $request->request->get('last_name')))
                   ->setDepartment(trim((string) $request->request->get('department')))
                   ->setTitle((string) $request->request->get('title'));
            }

            $em->flush();

            $auditService->log('USER_UPDATE', 'User', $user->getId(), null, [
                'username' => $user->getUsername(),
                'email' => $user->getEmail(),
                'isActive' => $user->isActive(),
            ]);

            $this->addFlash('success', 'User profile updated.');
            return $this->redirectToRoute('admin_users');
        }

        return $this->render('admin/users/edit.html.twig', [
            'editUser' => $user,
            'sections' => $sections,
        ]);
    }

    // ==========================================
    // SUBJECTS CRUD
    // ==========================================
    #[Route('/subjects', name: 'admin_subjects')]
    public function subjects(
        Request $request,
        SubjectRepository $subjectRepo,
        EntityManagerInterface $em,
        AuditService $auditService
    ): Response {
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('new_subject', (string) $request->request->get('_token'))) {
                $this->addFlash('danger', 'Invalid security token.');
                return $this->redirectToRoute('admin_subjects');
            }

            $code = trim((string) $request->request->get('code'));
            $title = trim((string) $request->request->get('title'));
            $desc = trim((string) $request->request->get('description'));
            $units = (int) $request->request->get('units');

            $subject = (new Subject())
                ->setCode($code)
                ->setTitle($title)
                ->setDescription($desc)
                ->setUnits($units ?: 3);

            $em->persist($subject);
            $em->flush();

            $auditService->log('SUBJECT_CREATE', 'Subject', $subject->getId(), null, ['code' => $code]);
            $this->addFlash('success', sprintf('Subject %s created.', $subject->getCode()));
            return $this->redirectToRoute('admin_subjects');
        }

        return $this->render('admin/subjects/index.html.twig', [
            'subjects' => $subjectRepo->findBy([], ['code' => 'ASC']),
        ]);
    }

    #[Route('/subjects/{id}/edit', name: 'admin_subject_edit', requirements: ['id' => '\d+'])]
    public function editSubject(
        Subject $subject,
        Request $request,
        EntityManagerInterface $em
    ): Response {
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('edit_subject_' . $subject->getId(), (string) $request->request->get('_token'))) {
                $this->addFlash('danger', 'Invalid security token.');
                return $this->redirectToRoute('admin_subject_edit', ['id' => $subject->getId()]);
            }

            $subject->setCode(trim((string) $request->request->get('code')))
                ->setTitle(trim((string) $request->request->get('title')))
                ->setDescription(trim((string) $request->request->get('description')))
                ->setUnits((int) $request->request->get('units'));

            $em->flush();
            $this->addFlash('success', 'Subject updated successfully.');
            return $this->redirectToRoute('admin_subjects');
        }

        return $this->render('admin/subjects/edit.html.twig', [
            'subject' => $subject,
        ]);
    }

    // ==========================================
    // SECTIONS CRUD
    // ==========================================
    #[Route('/sections', name: 'admin_sections')]
    public function sections(
        Request $request,
        SectionRepository $sectionRepo,
        EntityManagerInterface $em,
        AuditService $auditService
    ): Response {
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('new_section', (string) $request->request->get('_token'))) {
                $this->addFlash('danger', 'Invalid security token.');
                return $this->redirectToRoute('admin_sections');
            }

            $name = trim((string) $request->request->get('name'));
            $ay = trim((string) $request->request->get('academic_year'));
            $sem = trim((string) $request->request->get('semester'));

            $section = (new Section())
                ->setName($name)
                ->setAcademicYear($ay)
                ->setSemester($sem);

            $em->persist($section);
            $em->flush();

            $auditService->log('SECTION_CREATE', 'Section', $section->getId(), null, ['name' => $name]);
            $this->addFlash('success', sprintf('Section %s created.', $section->getName()));
            return $this->redirectToRoute('admin_sections');
        }

        return $this->render('admin/sections/index.html.twig', [
            'sections' => $sectionRepo->findAll(),
        ]);
    }

    #[Route('/sections/{id}/roster', name: 'admin_section_roster', requirements: ['id' => '\d+'])]
    public function sectionRoster(
        Section $section,
        Request $request,
        StudentProfileRepository $studentRepo,
        EntityManagerInterface $em
    ): Response {
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('roster_action_' . $section->getId(), (string) $request->request->get('_token'))) {
                $this->addFlash('danger', 'Invalid security token.');
                return $this->redirectToRoute('admin_section_roster', ['id' => $section->getId()]);
            }

            $action = (string) $request->request->get('action');
            $studentId = (int) $request->request->get('student_id');
            $student = $studentRepo->find($studentId);

            if ($student) {
                if ($action === 'add') {
                    $student->setSection($section);
                    $this->addFlash('success', sprintf('%s added to %s.', $student->getFullName(), $section->getName()));
                } elseif ($action === 'remove') {
                    $student->setSection(null);
                    $this->addFlash('info', sprintf('%s removed from %s.', $student->getFullName(), $section->getName()));
                }
                $em->flush();
            }

            return $this->redirectToRoute('admin_section_roster', ['id' => $section->getId()]);
        }

        $allStudents = $studentRepo->findAll();
        $unassigned = array_filter($allStudents, fn($s) => $s->getSection() === null);

        return $this->render('admin/sections/roster.html.twig', [
            'section' => $section,
            'students' => $section->getStudents(),
            'unassignedStudents' => $unassigned,
        ]);
    }

    // ==========================================
    // GRADING PERIODS CRUD
    // ==========================================
    #[Route('/grading-periods', name: 'admin_grading_periods')]
    public function gradingPeriods(
        Request $request,
        GradingPeriodRepository $periodRepo,
        EntityManagerInterface $em
    ): Response {
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('new_period', (string) $request->request->get('_token'))) {
                $this->addFlash('danger', 'Invalid security token.');
                return $this->redirectToRoute('admin_grading_periods');
            }

            $code = trim((string) $request->request->get('code'));
            $name = trim((string) $request->request->get('name'));
            $order = (int) $request->request->get('sequence_order');

            $gp = (new GradingPeriod())
                ->setCode($code)
                ->setName($name)
                ->setSequenceOrder($order ?: 1)
                ->setIsActive(true);

            $em->persist($gp);
            $em->flush();

            $this->addFlash('success', sprintf('Grading period %s created.', $gp->getName()));
            return $this->redirectToRoute('admin_grading_periods');
        }

        return $this->render('admin/grading_periods/index.html.twig', [
            'periods' => $periodRepo->findOrdered(),
        ]);
    }

    // ==========================================
    // TEACHER ASSIGNMENTS (SectionSubjects)
    // ==========================================
    #[Route('/assignments', name: 'admin_assignments')]
    public function assignments(
        Request $request,
        SectionSubjectRepository $ssRepo,
        SectionRepository $sectionRepo,
        SubjectRepository $subjectRepo,
        TeacherProfileRepository $teacherRepo,
        EntityManagerInterface $em,
        AuditService $auditService
    ): Response {
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('new_assignment', (string) $request->request->get('_token'))) {
                $this->addFlash('danger', 'Invalid security token.');
                return $this->redirectToRoute('admin_assignments');
            }

            $sectionId = (int) $request->request->get('section_id');
            $subjectId = (int) $request->request->get('subject_id');
            $teacherId = (int) $request->request->get('teacher_id');

            $section = $sectionRepo->find($sectionId);
            $subject = $subjectRepo->find($subjectId);
            $teacher = $teacherRepo->find($teacherId);

            if ($section && $subject && $teacher) {
                $existing = $ssRepo->findOneBy(['section' => $section, 'subject' => $subject]);
                if ($existing) {
                    $existing->setTeacher($teacher);
                } else {
                    $ss = (new SectionSubject())
                        ->setSection($section)
                        ->setSubject($subject)
                        ->setTeacher($teacher);
                    $em->persist($ss);
                }
                $em->flush();

                $auditService->log('ASSIGNMENT_UPDATE', 'SectionSubject', 0, null, [
                    'section' => $section->getName(),
                    'subject' => $subject->getCode(),
                    'teacher' => $teacher->getFullName(),
                ]);

                $this->addFlash('success', sprintf('Assigned %s to %s in %s.', $teacher->getFullName(), $subject->getCode(), $section->getName()));
            }

            return $this->redirectToRoute('admin_assignments');
        }

        return $this->render('admin/assignments/index.html.twig', [
            'assignments' => $ssRepo->findAll(),
            'sections' => $sectionRepo->findAll(),
            'subjects' => $subjectRepo->findAll(),
            'teachers' => $teacherRepo->findAll(),
        ]);
    }

    #[Route('/assignments/{id}/delete', name: 'admin_assignment_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function deleteAssignment(
        SectionSubject $ss,
        Request $request,
        EntityManagerInterface $em
    ): Response {
        if ($this->isCsrfTokenValid('delete_assignment_' . $ss->getId(), (string) $request->request->get('_token'))) {
            $em->remove($ss);
            $em->flush();
            $this->addFlash('info', 'Class assignment removed.');
        }

        return $this->redirectToRoute('admin_assignments');
    }

    // ==========================================
    // AUDIT LOGS VIEWER
    // ==========================================
    #[Route('/audit-logs', name: 'admin_audit_logs')]
    public function auditLogs(Request $request, AuditLogRepository $auditRepo): Response
    {
        $actionFilter = $request->query->get('action');
        $logs = $auditRepo->findRecent(150);

        if ($actionFilter) {
            $logs = array_filter($logs, fn(AuditLog $a) => $a->getAction() === $actionFilter);
        }

        return $this->render('admin/audit_logs.html.twig', [
            'logs' => $logs,
            'currentAction' => $actionFilter,
        ]);
    }

    // ==========================================
    // REPORTS (Academic Progress & Export PDF/CSV)
    // ==========================================
    #[Route('/reports/academic-progress', name: 'admin_reports_progress')]
    public function academicProgressReport(
        Request $request,
        SectionRepository $sectionRepo,
        GradingPeriodRepository $periodRepo,
        StudentProfileRepository $studentRepo,
        GradeRepository $gradeRepo,
        IncompleteRecordRepository $incRepo
    ): Response {
        $sections = $sectionRepo->findAll();
        $periods = $periodRepo->findOrdered();

        $selectedSectionId = $request->query->getInt('section_id');
        $selectedSection = $selectedSectionId ? $sectionRepo->find($selectedSectionId) : ($sections[0] ?? null);

        $students = [];
        $progressData = [];

        if ($selectedSection) {
            $students = $selectedSection->getStudents()->toArray();
            usort($students, fn($a, $b) => strcmp($a->getLastName(), $b->getLastName()));

            foreach ($students as $st) {
                $grades = $gradeRepo->findByStudent($st);
                $incs = $incRepo->findByStudent($st);

                $pending = count(array_filter($incs, fn($i) => $i->getStatus() === IncompleteStatus::PENDING));
                $submitted = count(array_filter($incs, fn($i) => $i->getStatus() === IncompleteStatus::SUBMITTED));
                $completed = count(array_filter($incs, fn($i) => $i->getStatus() === IncompleteStatus::COMPLETED));

                $numericGrades = array_filter($grades, fn($g) => !$g->isInc() && $g->getNumericalValue() !== null);
                $gpa = count($numericGrades) > 0 ? round(array_sum(array_map(fn($g) => (float) $g->getNumericalValue(), $numericGrades)) / count($numericGrades), 2) : 0.0;

                $progressData[$st->getId()] = [
                    'student' => $st,
                    'totalGrades' => count($grades),
                    'gpa' => $gpa,
                    'pending' => $pending,
                    'submitted' => $submitted,
                    'completed' => $completed,
                    'status' => $pending > 0 ? 'NEEDS ATTENTION' : ($submitted > 0 ? 'UNDER REVIEW' : 'CLEAR'),
                ];
            }
        }

        $format = $request->query->get('format');
        if ($format === 'csv') {
            return $this->exportProgressCsv($selectedSection, $progressData);
        }

        if ($format === 'pdf') {
            return $this->exportProgressPdf($selectedSection, $progressData);
        }

        return $this->render('admin/reports/progress.html.twig', [
            'sections' => $sections,
            'selectedSection' => $selectedSection,
            'periods' => $periods,
            'progressData' => $progressData,
        ]);
    }

    private function exportProgressCsv(?Section $section, array $data): Response
    {
        $response = new StreamedResponse();
        $response->setCallback(function () use ($section, $data) {
            $handle = fopen('php://output', 'w+');
            fputcsv($handle, ['AC.TRACK — Academic Progress & Incomplete Grade Resolution Report'], ',', '"', "\\");
            fputcsv($handle, ['Section', $section ? $section->getName() : 'All', 'Generated At', date('Y-m-d H:i:s')], ',', '"', "\\");
            fputcsv($handle, [], ',', '"', "\\");
            fputcsv($handle, ['Student ID', 'Last Name', 'First Name', 'Year Level', 'Recorded Grades', 'GPA Average', 'Pending INCs', 'Submitted INCs', 'Completed INCs', 'Overall Standing'], ',', '"', "\\");

            foreach ($data as $item) {
                /** @var StudentProfile $st */
                $st = $item['student'];
                fputcsv($handle, [
                    $st->getStudentNumber(),
                    $st->getLastName(),
                    $st->getFirstName(),
                    $st->getYearLevel(),
                    $item['totalGrades'],
                    $item['gpa'],
                    $item['pending'],
                    $item['submitted'],
                    $item['completed'],
                    $item['status'],
                ], ',', '"', "\\");
            }
            fclose($handle);
        });

        $filename = sprintf('AcademicProgress-%s-%s.csv', $section ? $section->getName() : 'all', date('Ymd'));
        $response->headers->set('Content-Type', 'text/csv; charset=utf-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');

        return $response;
    }

    private function exportProgressPdf(?Section $section, array $data): Response
    {
        $html = $this->renderView('admin/reports/progress_pdf.html.twig', [
            'section' => $section,
            'progressData' => $data,
            'generatedAt' => new \DateTime(),
        ]);

        $options = new Options();
        $options->set('isRemoteEnabled', true);
        $options->set('defaultFont', 'Helvetica');
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();

        return new Response(
            $dompdf->output(),
            Response::HTTP_OK,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => sprintf('attachment; filename="AcademicProgress-%s.pdf"', $section ? $section->getName() : 'all'),
            ]
        );
    }
}
