<?php

namespace App\Twig;

use App\Entity\User;
use App\Repository\NotificationRepository;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

class AppExtension extends AbstractExtension
{
    public function __construct(private readonly NotificationRepository $notifications)
    {
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('duration', [$this, 'formatDuration']),
            new TwigFilter('role_label', [$this, 'formatRole']),
            new TwigFilter('status_label', [$this, 'formatStatus']),
        ];
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('notification_summary', [$this, 'getNotificationSummary']),
        ];
    }

    /**
     * @return array{latest: list<object>, unread_count: int}
     */
    public function getNotificationSummary(mixed $user): array
    {
        if (!$user instanceof User) {
            return [
                'latest' => [],
                'unread_count' => 0,
            ];
        }

        return [
            'latest' => $this->notifications->findLatestForRecipient($user, 6),
            'unread_count' => $this->notifications->countUnreadForRecipient($user),
        ];
    }

    public function formatDuration(?int $seconds): string
    {
        $seconds = max(0, $seconds ?? 0);
        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);
        $remainingSeconds = $seconds % 60;

        return sprintf('%02d:%02d:%02d', $hours, $minutes, $remainingSeconds);
    }

    public function formatRole(string $role): string
    {
        return match ($role) {
            'ROLE_SUPER_ADMIN' => 'Super Admin',
            'ROLE_EMPLOYEE' => 'Employee',
            default => ucfirst(strtolower(str_replace(['ROLE_', '_'], ['', ' '], $role))),
        };
    }

    public function formatStatus(string $status): string
    {
        return ucfirst(str_replace('_', ' ', $status));
    }
}
