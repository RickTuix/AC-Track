<?php

namespace App\Service;

use App\Entity\Notification;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

class NotificationService
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    public function notify(User $recipient, string $title, string $message, ?string $link = null, string $type = 'INFO'): Notification
    {
        $notification = new Notification();
        $notification->setUser($recipient)
            ->setTitle($title)
            ->setMessage($message)
            ->setLink($link)
            ->setType($type)
            ->setIsRead(false);

        $this->em->persist($notification);
        $this->em->flush();

        return $notification;
    }

    public function markAsRead(Notification $notification): void
    {
        $notification->setIsRead(true);
        $this->em->flush();
    }

    public function markAllAsRead(User $user): void
    {
        $this->em->createQueryBuilder()
            ->update(Notification::class, 'n')
            ->set('n.isRead', 'true')
            ->where('n.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->execute();
    }
}
