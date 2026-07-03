<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/directory', name: 'app_directory_')]
#[IsGranted('ROLE_USER')]
class DirectoryController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Request $request, UserRepository $users): Response
    {
        $query = trim((string) $request->query->get('q', ''));
        $role = trim((string) $request->query->get('role', ''));
        $employees = $this->filterUsers($users->findBy(['isActive' => true], ['fullName' => 'ASC']), $query, $role);

        return $this->render('directory/index.html.twig', [
            'directory_users' => $employees,
            'directory_query' => $query,
            'directory_role' => $role,
            'employee_count' => count(array_filter($employees, static fn (User $user): bool => $user->getRole() === User::ROLE_EMPLOYEE)),
            'admin_count' => count(array_filter($employees, static fn (User $user): bool => $user->getRole() === User::ROLE_SUPER_ADMIN)),
        ]);
    }

    /**
     * @param list<User> $users
     *
     * @return list<User>
     */
    private function filterUsers(array $users, string $query, string $role): array
    {
        return array_values(array_filter($users, static function (User $user) use ($query, $role): bool {
            if ($role !== '' && $user->getRole() !== $role) {
                return false;
            }

            if ($query === '') {
                return true;
            }

            $haystack = strtolower(implode(' ', [
                $user->getFullName(),
                $user->getEmail(),
                (string) $user->getJobTitle(),
                implode(' ', $user->getSkillList()),
            ]));

            return str_contains($haystack, strtolower($query));
        }));
    }
}
