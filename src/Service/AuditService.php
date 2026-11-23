<?php

namespace App\Service;

use App\Entity\AuditLog;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;

class AuditService
{
    public function __construct(
        private EntityManagerInterface $em,
        private Security $security,
        private RequestStack $requestStack
    ) {
    }

    public function log(string $action, string $entityType, int $entityId, ?array $oldValue = null, ?array $newValue = null, ?User $user = null): AuditLog
    {
        if ($user === null) {
            $currentUser = $this->security->getUser();
            if ($currentUser instanceof User) {
                $user = $currentUser;
            }
        }

        $ipAddress = null;
        $request = $this->requestStack->getMainRequest();
        if ($request) {
            $ipAddress = $request->getClientIp();
        }

        $log = new AuditLog();
        $log->setAction($action)
            ->setEntityType($entityType)
            ->setEntityId($entityId)
            ->setOldValue($oldValue)
            ->setNewValue($newValue)
            ->setUser($user)
            ->setIpAddress($ipAddress);

        $this->em->persist($log);
        $this->em->flush();

        return $log;
    }
}
