<?php

namespace App\Repository;

use App\Entity\IncompleteRecord;
use App\Entity\StudentProfile;
use App\Entity\TeacherProfile;
use App\Enum\IncompleteStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<IncompleteRecord>
 */
class IncompleteRecordRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, IncompleteRecord::class);
    }

    /**
     * @return IncompleteRecord[]
     */
    public function findByStudent(StudentProfile $student, ?IncompleteStatus $status = null): array
    {
        $qb = $this->createQueryBuilder('r')
            ->andWhere('r.student = :student')
            ->setParameter('student', $student)
            ->join('r.subject', 'sub')
            ->join('r.gradingPeriod', 'gp')
            ->orderBy('r.deadline', 'ASC');

        if ($status !== null) {
            $qb->andWhere('r.status = :status')
               ->setParameter('status', $status);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * @return IncompleteRecord[]
     */
    public function findByTeacher(TeacherProfile $teacher, ?IncompleteStatus $status = null): array
    {
        $qb = $this->createQueryBuilder('r')
            ->andWhere('r.teacher = :teacher')
            ->setParameter('teacher', $teacher)
            ->join('r.student', 'st')
            ->join('r.subject', 'sub')
            ->orderBy('r.deadline', 'ASC');

        if ($status !== null) {
            $qb->andWhere('r.status = :status')
               ->setParameter('status', $status);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * @return IncompleteRecord[]
     */
    public function findApproachingDeadlines(int $hours = 48): array
    {
        $now = new \DateTime();
        $target = (new \DateTime())->modify(sprintf('+%d hours', $hours));

        return $this->createQueryBuilder('r')
            ->andWhere('r.status = :pending')
            ->andWhere('r.deadline >= :now')
            ->andWhere('r.deadline <= :target')
            ->setParameter('pending', IncompleteStatus::PENDING)
            ->setParameter('now', $now)
            ->setParameter('target', $target)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return IncompleteRecord[]
     */
    public function findOverduePending(): array
    {
        $now = new \DateTime();

        return $this->createQueryBuilder('r')
            ->andWhere('r.status = :pending')
            ->andWhere('r.deadline < :now')
            ->setParameter('pending', IncompleteStatus::PENDING)
            ->setParameter('now', $now)
            ->getQuery()
            ->getResult();
    }
}
