<?php

namespace App\Controller;

use App\Entity\Notification;
use App\Entity\User;
use App\Repository\NotificationRepository;
use App\Service\NotificationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/notifications')]
#[IsGranted('ROLE_USER')]
class NotificationController extends AbstractController
{
    #[Route('', name: 'app_notifications')]
    public function index(NotificationRepository $notifRepo): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $notifications = $notifRepo->findForUser($user, 100);
        $unreadCount = $notifRepo->countUnreadForUser($user);

        return $this->render('notification/index.html.twig', [
            'notifications' => $notifications,
            'unreadCount' => $unreadCount,
        ]);
    }

    #[Route('/{id}/read', name: 'app_notification_read', requirements: ['id' => '\d+'])]
    public function markRead(Notification $notification, NotificationService $notifService): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        if ($notification->getUser()->getId() !== $user->getId()) {
            throw $this->createAccessDeniedException();
        }

        $notifService->markAsRead($notification);

        if ($notification->getLink()) {
            return $this->redirect($notification->getLink());
        }

        return $this->redirectToRoute('app_notifications');
    }

    #[Route('/read-all', name: 'app_notifications_read_all', methods: ['POST'])]
    public function markAllRead(Request $request, NotificationService $notifService): Response
    {
        if (!$this->isCsrfTokenValid('read_all_notifs', (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Invalid security token.');
            return $this->redirectToRoute('app_notifications');
        }

        /** @var User $user */
        $user = $this->getUser();
        $notifService->markAllAsRead($user);
        $this->addFlash('success', 'All notifications marked as read.');

        return $this->redirectToRoute('app_notifications');
    }
}
