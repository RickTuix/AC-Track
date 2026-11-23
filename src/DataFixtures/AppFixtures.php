<?php

namespace App\DataFixtures;

use App\Entity\AuditLog;
use App\Entity\Grade;
use App\Entity\GradingPeriod;
use App\Entity\IncompleteRecord;
use App\Entity\Notification;
use App\Entity\Section;
use App\Entity\SectionSubject;
use App\Entity\StudentProfile;
use App\Entity\Subject;
use App\Entity\TeacherProfile;
use App\Entity\User;
use App\Enum\IncompleteStatus;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    public function __construct(private UserPasswordHasherInterface $hasher)
    {
    }

    public function load(ObjectManager $manager): void
    {
        // 1. Grading Periods (4)
        $periodsData = [
            ['code' => 'PRELIM', 'name' => 'Prelim', 'order' => 1, 'start' => '-90 days', 'end' => '-60 days'],
            ['code' => 'MIDTERM', 'name' => 'Midterm', 'order' => 2, 'start' => '-60 days', 'end' => '-30 days'],
            ['code' => 'SEMIFINAL', 'name' => 'Semi-Final', 'order' => 3, 'start' => '-30 days', 'end' => '-5 days'],
            ['code' => 'FINAL', 'name' => 'Final', 'order' => 4, 'start' => '-5 days', 'end' => '+30 days'],
        ];

        /** @var array<string, GradingPeriod> $periods */
        $periods = [];
        foreach ($periodsData as $p) {
            $period = new GradingPeriod();
            $period->setCode($p['code'])
                ->setName($p['name'])
                ->setSequenceOrder($p['order'])
                ->setStartDate(new \DateTime($p['start']))
                ->setEndDate(new \DateTime($p['end']))
                ->setIsActive(true);
            $manager->persist($period);
            $periods[$p['code']] = $period;
        }

        // 2. Sections (2)
        $sectionIT = new Section();
        $sectionIT->setName('BSIT-3A')
            ->setAcademicYear('2024-2025')
            ->setSemester('1st Semester');
        $manager->persist($sectionIT);

        $sectionCS = new Section();
        $sectionCS->setName('BSCS-3A')
            ->setAcademicYear('2024-2025')
            ->setSemester('1st Semester');
        $manager->persist($sectionCS);

        // 3. Subjects (6)
        $subjectsData = [
            ['code' => 'CS101', 'title' => 'Introduction to Computing', 'desc' => 'Foundations of computer systems and software.', 'units' => 3],
            ['code' => 'CS102', 'title' => 'Data Structures & Algorithms', 'desc' => 'Core algorithms, complexity, and data organization.', 'units' => 3],
            ['code' => 'IT201', 'title' => 'Web Systems & Technologies', 'desc' => 'Modern client-server architecture, protocols, and web apps.', 'units' => 3],
            ['code' => 'IT202', 'title' => 'Database Management Systems', 'desc' => 'Relational database modeling, SQL, and normalization.', 'units' => 3],
            ['code' => 'MATH101', 'title' => 'Discrete Mathematics', 'desc' => 'Logic, sets, relations, graphs, and combinatorial proofs.', 'units' => 3],
            ['code' => 'CS301', 'title' => 'Software Engineering', 'desc' => 'Software lifecycle, architecture, testing, and project management.', 'units' => 3],
        ];

        /** @var array<string, Subject> $subjects */
        $subjects = [];
        foreach ($subjectsData as $s) {
            $subject = new Subject();
            $subject->setCode($s['code'])
                ->setTitle($s['title'])
                ->setDescription($s['desc'])
                ->setUnits($s['units']);
            $manager->persist($subject);
            $subjects[$s['code']] = $subject;
        }

        // 4. Admin User (1)
        $adminUser = new User();
        $adminUser->setUsername('admin')
            ->setEmail('admin@actrack.edu')
            ->setRoles(['ROLE_ADMIN'])
            ->setIsActive(true);
        $adminUser->setPassword($this->hasher->hashPassword($adminUser, 'password123'));
        $manager->persist($adminUser);

        // 5. Teachers (3)
        $teachersData = [
            [
                'username' => 'teacher.santos',
                'email' => 'santos@actrack.edu',
                'empNo' => 'EMP-2024-001',
                'firstName' => 'Maria',
                'lastName' => 'Santos',
                'dept' => 'Computer Science',
                'title' => 'Prof.',
            ],
            [
                'username' => 'teacher.reyes',
                'email' => 'reyes@actrack.edu',
                'empNo' => 'EMP-2024-002',
                'firstName' => 'Juan',
                'lastName' => 'Reyes',
                'dept' => 'Information Technology',
                'title' => 'Engr.',
            ],
            [
                'username' => 'teacher.cruz',
                'email' => 'cruz@actrack.edu',
                'empNo' => 'EMP-2024-003',
                'firstName' => 'Elena',
                'lastName' => 'Cruz',
                'dept' => 'Mathematics & Computing',
                'title' => 'Dr.',
            ],
        ];

        /** @var TeacherProfile[] $teachers */
        $teachers = [];
        foreach ($teachersData as $t) {
            $user = new User();
            $user->setUsername($t['username'])
                ->setEmail($t['email'])
                ->setRoles(['ROLE_TEACHER'])
                ->setIsActive(true);
            $user->setPassword($this->hasher->hashPassword($user, 'password123'));
            $manager->persist($user);

            $profile = new TeacherProfile();
            $profile->setUser($user)
                ->setEmployeeNumber($t['empNo'])
                ->setFirstName($t['firstName'])
                ->setLastName($t['lastName'])
                ->setDepartment($t['dept'])
                ->setTitle($t['title']);
            $manager->persist($profile);

            $teachers[$t['username']] = $profile;
        }

        // 6. SectionSubject Assignments
        // Santos -> CS101 & CS102 for BSCS-3A
        $ss1 = (new SectionSubject())->setSection($sectionCS)->setSubject($subjects['CS101'])->setTeacher($teachers['teacher.santos']);
        $ss2 = (new SectionSubject())->setSection($sectionCS)->setSubject($subjects['CS102'])->setTeacher($teachers['teacher.santos']);
        // Reyes -> IT201 & IT202 for BSIT-3A
        $ss3 = (new SectionSubject())->setSection($sectionIT)->setSubject($subjects['IT201'])->setTeacher($teachers['teacher.reyes']);
        $ss4 = (new SectionSubject())->setSection($sectionIT)->setSubject($subjects['IT202'])->setTeacher($teachers['teacher.reyes']);
        // Cruz -> MATH101 for BSIT-3A & BSCS-3A, CS301 for BSCS-3A
        $ss5 = (new SectionSubject())->setSection($sectionIT)->setSubject($subjects['MATH101'])->setTeacher($teachers['teacher.cruz']);
        $ss6 = (new SectionSubject())->setSection($sectionCS)->setSubject($subjects['MATH101'])->setTeacher($teachers['teacher.cruz']);
        $ss7 = (new SectionSubject())->setSection($sectionCS)->setSubject($subjects['CS301'])->setTeacher($teachers['teacher.cruz']);

        $manager->persist($ss1);
        $manager->persist($ss2);
        $manager->persist($ss3);
        $manager->persist($ss4);
        $manager->persist($ss5);
        $manager->persist($ss6);
        $manager->persist($ss7);

        // 7. Students (15)
        $studentsData = [
            ['student01', 'alvarez@actrack.edu', '2024-00101', 'Carl', 'Alvarez', '3rd Year', $sectionIT],
            ['student02', 'bautista@actrack.edu', '2024-00102', 'Beatrice', 'Bautista', '3rd Year', $sectionIT],
            ['student03', 'castillo@actrack.edu', '2024-00103', 'Christian', 'Castillo', '3rd Year', $sectionIT],
            ['student04', 'delacruz@actrack.edu', '2024-00104', 'Diana', 'Dela Cruz', '3rd Year', $sectionIT],
            ['student05', 'estrada@actrack.edu', '2024-00105', 'Ethan', 'Estrada', '3rd Year', $sectionIT],
            ['student06', 'flores@actrack.edu', '2024-00106', 'Fiona', 'Flores', '3rd Year', $sectionIT],
            ['student07', 'garcia@actrack.edu', '2024-00107', 'Gabriel', 'Garcia', '3rd Year', $sectionIT],
            ['student08', 'hernandez@actrack.edu', '2024-00108', 'Hannah', 'Hernandez', '3rd Year', $sectionIT],
            ['student09', 'ignacio@actrack.edu', '2024-00109', 'Ian', 'Ignacio', '3rd Year', $sectionCS],
            ['student10', 'javier@actrack.edu', '2024-00110', 'Julia', 'Javier', '3rd Year', $sectionCS],
            ['student11', 'lim@actrack.edu', '2024-00111', 'Lucas', 'Lim', '3rd Year', $sectionCS],
            ['student12', 'mendoza@actrack.edu', '2024-00112', 'Mia', 'Mendoza', '3rd Year', $sectionCS],
            ['student13', 'navarro@actrack.edu', '2024-00113', 'Noah', 'Navarro', '3rd Year', $sectionCS],
            ['student14', 'ocampo@actrack.edu', '2024-00114', 'Olivia', 'Ocampo', '3rd Year', $sectionCS],
            ['student15', 'perez@actrack.edu', '2024-00115', 'Paul', 'Perez', '3rd Year', $sectionCS],
        ];

        /** @var StudentProfile[] $students */
        $students = [];
        foreach ($studentsData as $st) {
            $user = new User();
            $user->setUsername($st[0])
                ->setEmail($st[1])
                ->setRoles(['ROLE_STUDENT'])
                ->setIsActive(true);
            $user->setPassword($this->hasher->hashPassword($user, 'password123'));
            $manager->persist($user);

            $profile = new StudentProfile();
            $profile->setUser($user)
                ->setStudentNumber($st[2])
                ->setFirstName($st[3])
                ->setLastName($st[4])
                ->setYearLevel($st[5])
                ->setSection($st[6]);
            $manager->persist($profile);

            $students[$st[0]] = $profile;
        }

        // Flush so we have foreign keys available
        $manager->flush();

        // 8. Grades and Incomplete Records
        // Assign realistic grades across periods for subjects
        $studentSubjectsMap = [
            'BSIT-3A' => [$subjects['IT201'], $subjects['IT202'], $subjects['MATH101']],
            'BSCS-3A' => [$subjects['CS101'], $subjects['CS102'], $subjects['MATH101'], $subjects['CS301']],
        ];

        $subjectTeacherMap = [
            'CS101' => $teachers['teacher.santos'],
            'CS102' => $teachers['teacher.santos'],
            'IT201' => $teachers['teacher.reyes'],
            'IT202' => $teachers['teacher.reyes'],
            'MATH101' => $teachers['teacher.cruz'],
            'CS301' => $teachers['teacher.cruz'],
        ];

        // Seed grades for Prelim, Midterm, Semi-Final
        $evalPeriods = [$periods['PRELIM'], $periods['MIDTERM'], $periods['SEMIFINAL']];

        $incCandidates = [
            ['student' => 'student01', 'subject' => 'IT201', 'period' => 'MIDTERM', 'category' => 'Assignment', 'title' => 'Lab Exercise 4: REST API Implementation', 'status' => 'PENDING', 'deadline' => '+10 days'],
            ['student' => 'student01', 'subject' => 'IT202', 'period' => 'SEMIFINAL', 'category' => 'Project', 'title' => 'Database Schema & Query Optimization Report', 'status' => 'PENDING', 'deadline' => '+1 day'], // Approaching
            ['student' => 'student02', 'subject' => 'IT201', 'period' => 'PRELIM', 'category' => 'Examination', 'title' => 'Prelim Practical Exam Make-up', 'status' => 'COMPLETED', 'deadline' => '-10 days'],
            ['student' => 'student03', 'subject' => 'MATH101', 'period' => 'MIDTERM', 'category' => 'Assignment', 'title' => 'Problem Set 3: Graph Theory & Trees', 'status' => 'SUBMITTED', 'deadline' => '+5 days'],
            ['student' => 'student04', 'subject' => 'IT202', 'period' => 'PRELIM', 'category' => 'Activity', 'title' => 'ERD Case Study: Hospital Management System', 'status' => 'PENDING', 'deadline' => '-5 days'], // Overdue
            ['student' => 'student09', 'subject' => 'CS102', 'period' => 'MIDTERM', 'category' => 'Project', 'title' => 'Binary Search Tree & AVL Visualizer', 'status' => 'PENDING', 'deadline' => '+14 days'],
            ['student' => 'student10', 'subject' => 'CS301', 'period' => 'SEMIFINAL', 'category' => 'Project', 'title' => 'Sprint 2 Architectural Documentation', 'status' => 'SUBMITTED', 'deadline' => '+3 days'],
            ['student' => 'student11', 'subject' => 'CS101', 'period' => 'PRELIM', 'category' => 'Examination', 'title' => 'Computer Architecture Prelim Remediation Exam', 'status' => 'COMPLETED', 'deadline' => '-15 days'],
            ['student' => 'student12', 'subject' => 'MATH101', 'period' => 'MIDTERM', 'category' => 'Activity', 'title' => 'Mathematical Induction Proof Worksheets', 'status' => 'PENDING', 'deadline' => '-2 days'], // Overdue
        ];

        foreach ($students as $key => $student) {
            $secName = $student->getSection()->getName();
            $enrolledSubjects = $studentSubjectsMap[$secName];

            foreach ($enrolledSubjects as $sub) {
                foreach ($evalPeriods as $per) {
                    $grade = new Grade();
                    $grade->setStudent($student)
                        ->setSubject($sub)
                        ->setSection($student->getSection())
                        ->setGradingPeriod($per)
                        ->setUpdatedAt(new \DateTimeImmutable());

                    // Check if candidate for INC
                    $isCandidateInc = false;
                    foreach ($incCandidates as $cand) {
                        if ($cand['student'] === $key && $cand['subject'] === $sub->getCode() && $cand['period'] === $per->getCode()) {
                            $isCandidateInc = true;
                            break;
                        }
                    }

                    if ($isCandidateInc) {
                        $grade->setIsInc(true)
                            ->setNumericalValue(null)
                            ->setRemarks('Incomplete (INC)');
                    } else {
                        // Standard score between 75 and 96
                        $score = rand(76, 96) + (rand(0, 9) / 10);
                        $remarks = $score >= 75 ? 'Passed' : 'Failed';
                        $grade->setIsInc(false)
                            ->setNumericalValue((string) $score)
                            ->setRemarks($remarks);
                    }

                    $manager->persist($grade);
                }
            }
        }

        // Create IncompleteRecord instances
        foreach ($incCandidates as $cand) {
            $st = $students[$cand['student']];
            $sub = $subjects[$cand['subject']];
            $per = $periods[$cand['period']];
            $tch = $subjectTeacherMap[$cand['subject']];

            $inc = new IncompleteRecord();
            $inc->setStudent($st)
                ->setSubject($sub)
                ->setSection($st->getSection())
                ->setGradingPeriod($per)
                ->setTeacher($tch)
                ->setMissingCategory($cand['category'])
                ->setMissingItemTitle($cand['title'])
                ->setTeacherExplanation(sprintf('Student missed or has pending revisions for %s: %s.', $cand['category'], $cand['title']))
                ->setRequiredAction(sprintf('Review the rubric and submit completed work for %s on or before the set deadline.', $cand['title']))
                ->setDeadline(new \DateTime($cand['deadline']))
                ->setStatus(IncompleteStatus::from($cand['status']))
                ->setCreatedBy($tch->getUser())
                ->setUpdatedBy($tch->getUser());

            if ($cand['status'] === 'SUBMITTED') {
                $inc->setSubmissionNote('Completed all required sections as discussed in class. Please review.');
                $inc->setSubmittedAt(new \DateTime('-1 day'));
            } elseif ($cand['status'] === 'COMPLETED') {
                $inc->setSubmissionNote('Submitted make-up requirements with comprehensive solution.');
                $inc->setSubmittedAt(new \DateTime('-12 days'));
                $inc->setResolvedAt(new \DateTime('-10 days'));
            }

            $manager->persist($inc);

            // Create notification for student
            $notif = new Notification();
            $notif->setUser($st->getUser())
                ->setTitle(sprintf('Academic Notice: INC for %s', $sub->getCode()))
                ->setMessage(sprintf('%s recorded an INC requirement for %s (%s). Deadline: %s.', $tch->getFullName(), $cand['title'], $sub->getCode(), $inc->getDeadline()->format('M d, Y h:i A')))
                ->setLink('/student/incompletes')
                ->setType('INC_CREATED');
            $manager->persist($notif);

            // Create audit log for INC creation
            $audit = new AuditLog();
            $audit->setUser($tch->getUser())
                ->setAction('INC_CREATE')
                ->setEntityType('IncompleteRecord')
                ->setEntityId(0) // will be updated upon flush
                ->setNewValue([
                    'student' => $st->getFullName(),
                    'subject' => $sub->getCode(),
                    'title' => $cand['title'],
                    'status' => $cand['status'],
                    'deadline' => $inc->getDeadline()->format('Y-m-d H:i:s'),
                ]);
            $manager->persist($audit);
        }

        $manager->flush();
    }
}
