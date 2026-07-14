<?php
namespace App\Tests\Service;

use App\Entity\Task;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\NotificationService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class NotificationServiceTest extends TestCase
{
    public function testAssignmentNotifiesEveryNewAssigneeExceptActor(): void
    {
        $actor = $this->user(1, 'Actor'); $existing = $this->user(2, 'Existing'); $new = $this->user(3, 'New');
        $task = (new Task())->setTitle('Task')->addAssignee($actor)->addAssignee($existing)->addAssignee($new);
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('persist')->with(self::callback(fn ($notification) => $notification->getRecipient() === $new));
        $urls = $this->createStub(UrlGeneratorInterface::class); $urls->method('generate')->willReturn('/task');
        $service = new NotificationService($em, $this->createStub(UserRepository::class), $urls);

        $service->notifyTaskAssigned($task, $actor, [$existing]);
    }

    private function user(int $id, string $name): User
    {
        $user = (new User())->setFullName($name)->setEmail(strtolower($name).'@example.com');
        $property = new \ReflectionProperty(User::class, 'id'); $property->setValue($user, $id);
        return $user;
    }
}
