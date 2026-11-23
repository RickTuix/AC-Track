<?php

namespace App\Service;

use App\Entity\Grade;
use App\Entity\GradingPeriod;
use App\Entity\Section;
use App\Entity\StudentProfile;
use App\Entity\Subject;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

class GradeService
{
    public function __construct(
        private EntityManagerInterface $em,
        private AuditService $auditService
    ) {
    }

    public function recordGrade(
        StudentProfile $student,
        Subject $subject,
        Section $section,
        GradingPeriod $period,
        ?string $numericalValue,
        bool $isInc,
        ?string $remarks,
        User $currentUser
    ): Grade {
        $gradeRepo = $this->em->getRepository(Grade::class);
        $grade = $gradeRepo->findOneBy([
            'student' => $student,
            'subject' => $subject,
            'gradingPeriod' => $period,
        ]);

        $oldValues = null;
        if ($grade) {
            $oldValues = [
                'numericalValue' => $grade->getNumericalValue(),
                'isInc' => $grade->isInc(),
                'remarks' => $grade->getRemarks(),
            ];
        } else {
            $grade = new Grade();
            $grade->setStudent($student)
                ->setSubject($subject)
                ->setSection($section)
                ->setGradingPeriod($period);
            $this->em->persist($grade);
        }

        if ($isInc) {
            $grade->setIsInc(true);
            $grade->setNumericalValue(null);
            $grade->setRemarks($remarks ?: 'Incomplete (INC)');
        } else {
            $grade->setIsInc(false);
            $grade->setNumericalValue($numericalValue);
            if (!$remarks && $numericalValue !== null) {
                $val = (float) $numericalValue;
                $remarks = $val >= 75.0 ? 'Passed' : 'Failed';
            }
            $grade->setRemarks($remarks);
        }

        $grade->setUpdatedAt(new \DateTimeImmutable());
        $grade->setUpdatedBy($currentUser);

        $this->em->flush();

        $this->auditService->log(
            'GRADE_UPDATE',
            'Grade',
            $grade->getId(),
            $oldValues,
            [
                'student' => $student->getFullName(),
                'subject' => $subject->getCode(),
                'period' => $period->getName(),
                'numericalValue' => $grade->getNumericalValue(),
                'isInc' => $grade->isInc(),
                'remarks' => $grade->getRemarks(),
            ],
            $currentUser
        );

        return $grade;
    }
}
