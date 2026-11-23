<?php

namespace App\Repository;

use App\Entity\SectionSubject;
use App\Entity\TeacherProfile;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SectionSubject>
 */
class SectionSubjectRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SectionSubject::class);
    }

    /**
     * @return SectionSubject[]
     */
    public function findByTeacher(TeacherProfile $teacher): array
    {
        return $this->createQueryBuilder('ss')
            ->andWhere('ss.teacher = :teacher')
            ->setParameter('teacher', $teacher)
            ->join('ss.section', 's')
            ->join('ss.subject', 'sub')
            ->orderBy('s.name', 'ASC')
            ->addOrderBy('sub.code', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
