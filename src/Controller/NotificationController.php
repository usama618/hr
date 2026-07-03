<?php

namespace App\Controller;

use App\Entity\Notification;
use App\Entity\User;
use App\Repository\NotificationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class NotificationController extends AbstractController
{
    #[Route('/notifications', name: 'app_notifications', methods: ['GET'])]
    public function index(NotificationRepository $notifications): Response
    {
        $user = $this->getAuthenticatedUser();

        return $this->render('notifications/index.html.twig', [
            'notifications' => $notifications->findAllForRecipient($user),
            'unread_count' => $notifications->countUnreadForRecipient($user),
        ]);
    }

    #[Route('/notifications/poll', name: 'app_notifications_poll', methods: ['GET'])]
    public function poll(Request $request, NotificationRepository $notifications): JsonResponse
    {
        $user = $this->getAuthenticatedUser();
        $afterId = max(0, (int) $request->query->get('after', 0));
        $latestId = $afterId;
        $items = [];

        foreach ($notifications->findUnreadNewerThanForRecipient($user, $afterId) as $notification) {
            $notificationId = (int) $notification->getId();
            $latestId = max($latestId, $notificationId);
            $items[] = [
                'id' => $notificationId,
                'title' => $notification->getTitle(),
                'body' => $notification->getBody(),
                'url' => $this->generateUrl('app_notification_open', ['id' => $notificationId]),
                'createdAt' => $notification->getCreatedAt()->format('d.m. H:i'),
            ];
        }

        return $this->json([
            'latestId' => $latestId,
            'unreadCount' => $notifications->countUnreadForRecipient($user),
            'notifications' => $items,
        ]);
    }

    #[Route('/notifications/mark-all-read', name: 'app_notifications_mark_all_read', methods: ['POST'])]
    public function markAllRead(
        Request $request,
        NotificationRepository $notifications,
        EntityManagerInterface $entityManager,
    ): Response {
        $this->guardCsrf($request, 'notifications_mark_all_read');
        $user = $this->getAuthenticatedUser();

        foreach ($notifications->findUnreadForRecipient($user) as $notification) {
            $notification->markRead();
        }

        $entityManager->flush();
        $this->addFlash('success', 'Notifications marked as read.');

        return $this->redirectToRoute('app_notifications');
    }

    #[Route('/notifications/{id}/open', name: 'app_notification_open', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function open(Notification $notification, EntityManagerInterface $entityManager): Response
    {
        $user = $this->getAuthenticatedUser();
        $this->denyUnlessRecipient($notification, $user);

        $notification->markRead();
        $entityManager->flush();

        return $this->redirect($this->getSafeRedirectUrl($notification, $user));
    }

    private function getAuthenticatedUser(): User
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        return $user;
    }

    private function denyUnlessRecipient(Notification $notification, User $user): void
    {
        if ($notification->getRecipient()?->getId() !== $user->getId()) {
            throw $this->createAccessDeniedException();
        }
    }

    private function guardCsrf(Request $request, string $tokenId): void
    {
        if (!$this->isCsrfTokenValid($tokenId, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }
    }

    private function getSafeRedirectUrl(Notification $notification, User $user): string
    {
        $url = $notification->getUrl();
        if ($url !== '' && str_starts_with($url, '/') && !str_starts_with($url, '//')) {
            return $url;
        }

        return $this->generateUrl($user->getRole() === User::ROLE_SUPER_ADMIN ? 'admin_dashboard' : 'employee_dashboard');
    }
}
