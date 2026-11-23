<?php

namespace App\Repository;

use App\Entity\GradingPeriod;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<GradingPeriod>
 */
class GradingPeriodRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, GradingPeriod::class);
    }

    /**
     * @return GradingPeriod[]
     */
    public function findOrdered(): array
    {
        return $this->findBy([], ['sequenceOrder' => 'ASC']);
    }
}
