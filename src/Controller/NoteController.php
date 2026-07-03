<?php

namespace App\Controller;

use App\Entity\Note;
use App\Entity\User;
use App\Repository\NoteRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/notes', name: 'app_notes_')]
#[IsGranted('ROLE_USER')]
class NoteController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Request $request, NoteRepository $notes): Response
    {
        $user = $this->getAuthenticatedUser();
        $query = trim((string) $request->query->get('q', ''));
        $notebook = trim((string) $request->query->get('notebook', ''));
        $pinnedOnly = $request->query->getBoolean('pinned');
        $selectedId = max(0, (int) $request->query->get('id', 0));

        $filteredNotes = $notes->findForOwner(
            $user,
            $query !== '' ? $query : null,
            $notebook !== '' ? $notebook : null,
            $pinnedOnly,
        );
        $allNotes = $notes->findForOwner($user);
        $selectedNote = null;

        if ($selectedId > 0) {
            $selectedNote = $notes->findOneBy(['id' => $selectedId, 'owner' => $user]);
        }

        if (!$selectedNote && $filteredNotes !== []) {
            $selectedNote = $filteredNotes[0];
        }

        return $this->render('notes/index.html.twig', [
            'notes' => $filteredNotes,
            'all_notes' => $allNotes,
            'selected_note' => $selectedNote,
            'notebooks' => $notes->findNotebookNamesForOwner($user),
            'notebook_counts' => $this->countNotesByNotebook($allNotes),
            'note_query' => $query,
            'current_notebook' => $notebook,
            'pinned_only' => $pinnedOnly,
            'pinned_count' => $this->countPinnedNotes($allNotes),
        ]);
    }

    #[Route('/create', name: 'create', methods: ['POST'])]
    public function create(Request $request, EntityManagerInterface $entityManager): Response
    {
        $this->guardCsrf($request, 'note_create');
        $note = $this->buildNoteFromRequest($request)
            ->setOwner($this->getAuthenticatedUser());

        if ($note->getTitle() === '' && $note->getBody() === '') {
            $this->addFlash('error', 'Add a title or note content before saving.');

            return $this->redirectToRoute('app_notes_index');
        }

        if ($note->getTitle() === '') {
            $note->setTitle($this->deriveTitle($note->getBody()));
        }

        $entityManager->persist($note);
        $entityManager->flush();
        $this->addFlash('success', 'Note created.');

        return $this->redirectToRoute('app_notes_index', ['id' => $note->getId()]);
    }

    #[Route('/{id}/update', name: 'update', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function update(Note $note, Request $request, EntityManagerInterface $entityManager): Response
    {
        $this->denyUnlessOwner($note);
        $this->guardCsrf($request, 'note_update_'.$note->getId());

        $title = trim((string) $request->request->get('title', ''));
        $body = trim((string) $request->request->get('body', ''));

        if ($title === '' && $body === '') {
            $this->addFlash('error', 'A note cannot be empty.');

            return $this->redirectToRoute('app_notes_index', ['id' => $note->getId()]);
        }

        $note
            ->setTitle($title !== '' ? $title : $this->deriveTitle($body))
            ->setBody($body)
            ->setNotebook((string) $request->request->get('notebook', ''))
            ->setIsPinned($request->request->getBoolean('isPinned'));

        $entityManager->flush();
        $this->addFlash('success', 'Note saved.');

        return $this->redirectToRoute('app_notes_index', ['id' => $note->getId()]);
    }

    #[Route('/{id}/pin', name: 'pin', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function pin(Note $note, Request $request, EntityManagerInterface $entityManager): Response
    {
        $this->denyUnlessOwner($note);
        $this->guardCsrf($request, 'note_pin_'.$note->getId());

        $note->setIsPinned(!$note->isPinned());
        $entityManager->flush();

        return $this->redirectToRoute('app_notes_index', ['id' => $note->getId()]);
    }

    #[Route('/{id}/delete', name: 'delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(Note $note, Request $request, EntityManagerInterface $entityManager): Response
    {
        $this->denyUnlessOwner($note);
        $this->guardCsrf($request, 'note_delete_'.$note->getId());

        $entityManager->remove($note);
        $entityManager->flush();
        $this->addFlash('success', 'Note deleted.');

        return $this->redirectToRoute('app_notes_index');
    }

    private function buildNoteFromRequest(Request $request): Note
    {
        return (new Note())
            ->setTitle((string) $request->request->get('title', ''))
            ->setBody((string) $request->request->get('body', ''))
            ->setNotebook((string) $request->request->get('notebook', ''))
            ->setIsPinned($request->request->getBoolean('isPinned'));
    }

    /**
     * @param list<Note> $notes
     *
     * @return array<string, int>
     */
    private function countNotesByNotebook(array $notes): array
    {
        $counts = [];

        foreach ($notes as $note) {
            $notebook = $note->getNotebook();
            if ($notebook) {
                $counts[$notebook] = ($counts[$notebook] ?? 0) + 1;
            }
        }

        ksort($counts);

        return $counts;
    }

    /**
     * @param list<Note> $notes
     */
    private function countPinnedNotes(array $notes): int
    {
        return count(array_filter($notes, static fn (Note $note): bool => $note->isPinned()));
    }

    private function deriveTitle(string $body): string
    {
        $title = trim((string) preg_replace('/\s+/', ' ', $body));

        return substr($title !== '' ? $title : 'Untitled note', 0, 160);
    }

    private function getAuthenticatedUser(): User
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        return $user;
    }

    private function denyUnlessOwner(Note $note): void
    {
        if ($note->getOwner()?->getId() !== $this->getAuthenticatedUser()->getId()) {
            throw $this->createAccessDeniedException();
        }
    }

    private function guardCsrf(Request $request, string $tokenId): void
    {
        if (!$this->isCsrfTokenValid($tokenId, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }
    }
}
