<?php

namespace App\Repository;

use App\Entity\Grade;
use App\Entity\GradingPeriod;
use App\Entity\Section;
use App\Entity\StudentProfile;
use App\Entity\Subject;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Grade>
 */
class GradeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Grade::class);
    }

    /**
     * @return Grade[]
     */
    public function findByStudent(StudentProfile $student): array
    {
        return $this->createQueryBuilder('g')
            ->andWhere('g.student = :student')
            ->setParameter('student', $student)
            ->join('g.gradingPeriod', 'gp')
            ->join('g.subject', 'sub')
            ->orderBy('sub.code', 'ASC')
            ->addOrderBy('gp.sequenceOrder', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Grade[]
     */
    public function findBySectionAndSubject(Section $section, Subject $subject, ?GradingPeriod $period = null): array
    {
        $qb = $this->createQueryBuilder('g')
            ->andWhere('g.section = :section')
            ->andWhere('g.subject = :subject')
            ->setParameter('section', $section)
            ->setParameter('subject', $subject)
            ->join('g.student', 'st')
            ->orderBy('st.lastName', 'ASC')
            ->addOrderBy('st.firstName', 'ASC');

        if ($period) {
            $qb->andWhere('g.gradingPeriod = :period')
               ->setParameter('period', $period);
        }

        return $qb->getQuery()->getResult();
    }
}
