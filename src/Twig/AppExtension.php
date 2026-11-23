<?php

namespace App\Twig;

use App\Entity\User;
use App\Enum\IncompleteStatus;
use App\Repository\NotificationRepository;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

class AppExtension extends AbstractExtension
{
    public function __construct(private NotificationRepository $notificationRepository)
    {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('unread_notifications_count', [$this, 'getUnreadCount']),
            new TwigFunction('status_badge', [$this, 'renderStatusBadge'], ['is_safe' => ['html']]),
        ];
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('status_label', [$this, 'getStatusLabel']),
        ];
    }

    public function getUnreadCount(?User $user): int
    {
        if (!$user) {
            return 0;
        }

        return $this->notificationRepository->countUnreadForUser($user);
    }

    public function renderStatusBadge(IncompleteStatus|string $status): string
    {
        $statusEnum = $status instanceof IncompleteStatus ? $status : IncompleteStatus::tryFrom($status);
        if (!$statusEnum) {
            return '<span class="badge bg-secondary">Unknown</span>';
        }

        $icon = match ($statusEnum) {
            IncompleteStatus::PENDING => '<i class="bi bi-hourglass-split me-1" aria-hidden="true"></i>',
            IncompleteStatus::SUBMITTED => '<i class="bi bi-arrow-up-circle-fill me-1" aria-hidden="true"></i>',
            IncompleteStatus::COMPLETED => '<i class="bi bi-check-circle-fill me-1" aria-hidden="true"></i>',
        };

        return sprintf(
            '<span class="badge %s d-inline-flex align-items-center px-2 py-1 fs-7 fw-semibold">%s%s</span>',
            $statusEnum->badgeClass(),
            $icon,
            htmlspecialchars($statusEnum->label(), ENT_QUOTES, 'UTF-8')
        );
    }

    public function getStatusLabel(IncompleteStatus|string $status): string
    {
        $statusEnum = $status instanceof IncompleteStatus ? $status : IncompleteStatus::tryFrom($status);
        return $statusEnum ? $statusEnum->label() : (string) $status;
    }
}
