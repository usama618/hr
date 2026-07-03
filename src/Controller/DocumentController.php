<?php

namespace App\Controller;

use App\Entity\EmployeeDocument;
use App\Entity\User;
use App\Repository\EmployeeDocumentRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/documents', name: 'app_documents_')]
#[IsGranted('ROLE_USER')]
class DocumentController extends AbstractController
{
    private const CATEGORY_LABELS = [
        EmployeeDocument::CATEGORY_POLICY => 'Policy',
        EmployeeDocument::CATEGORY_CONTRACT => 'Contract',
        EmployeeDocument::CATEGORY_PAYSLIP => 'Payslip',
        EmployeeDocument::CATEGORY_CERTIFICATE => 'Certificate',
        EmployeeDocument::CATEGORY_OTHER => 'Other',
    ];

    private const ALLOWED_MIME_TYPES = [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'image/jpeg',
        'image/png',
        'image/webp',
        'text/plain',
    ];

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Request $request, EmployeeDocumentRepository $documents, UserRepository $users): Response
    {
        $user = $this->getAuthenticatedUser();
        $isAdmin = $this->isGranted(User::ROLE_SUPER_ADMIN);
        $query = trim((string) $request->query->get('q', ''));
        $category = trim((string) $request->query->get('category', ''));
        $visibleDocuments = $documents->findVisibleForUser(
            $user,
            $isAdmin,
            $query !== '' ? $query : null,
            $category !== '' ? $category : null,
        );

        return $this->render('documents/index.html.twig', [
            'documents' => $visibleDocuments,
            'document_query' => $query,
            'document_category' => $category,
            'document_categories' => self::CATEGORY_LABELS,
            'document_employees' => $isAdmin ? $users->findBy([], ['fullName' => 'ASC']) : [],
            'can_upload_company_documents' => $isAdmin,
            'total_size' => array_sum(array_map(static fn (EmployeeDocument $document): int => $document->getFileSize(), $visibleDocuments)),
        ]);
    }

    #[Route('/upload', name: 'upload', methods: ['POST'])]
    public function upload(
        Request $request,
        UserRepository $users,
        EntityManagerInterface $entityManager,
        KernelInterface $kernel,
    ): Response {
        $this->guardCsrf($request, 'document_upload');
        $user = $this->getAuthenticatedUser();
        $file = $request->files->get('document_file');

        if (!$file instanceof UploadedFile || !$file->isValid()) {
            $this->addFlash('error', 'Choose a valid document file.');

            return $this->redirectToRoute('app_documents_index');
        }

        $mimeType = (string) ($file->getMimeType() ?: 'application/octet-stream');
        if (!in_array($mimeType, self::ALLOWED_MIME_TYPES, true)) {
            $this->addFlash('error', 'This file type is not allowed.');

            return $this->redirectToRoute('app_documents_index');
        }

        if ($file->getSize() !== false && $file->getSize() > 12 * 1024 * 1024) {
            $this->addFlash('error', 'Documents must be 12 MB or smaller.');

            return $this->redirectToRoute('app_documents_index');
        }

        $owner = $user;
        if ($this->isGranted(User::ROLE_SUPER_ADMIN)) {
            $ownerId = (int) $request->request->get('owner_id', 0);
            $owner = $ownerId > 0 ? $users->find($ownerId) : null;

            if ($ownerId > 0 && !$owner) {
                $this->addFlash('error', 'Choose a valid document owner.');

                return $this->redirectToRoute('app_documents_index');
            }
        }

        $originalName = $file->getClientOriginalName() ?: 'document';
        $extension = strtolower((string) ($file->guessExtension() ?: pathinfo($originalName, PATHINFO_EXTENSION) ?: 'bin'));
        $extension = preg_replace('/[^a-z0-9]/', '', $extension) ?: 'bin';
        $storedName = sprintf('%s.%s', bin2hex(random_bytes(16)), $extension);
        $uploadDir = $this->getDocumentStorageDir($kernel);

        $file->move($uploadDir, $storedName);

        $title = trim((string) $request->request->get('title'));
        $document = (new EmployeeDocument())
            ->setOwner($owner)
            ->setUploadedBy($user)
            ->setTitle($title !== '' ? $title : pathinfo($originalName, PATHINFO_FILENAME))
            ->setCategory((string) $request->request->get('category'))
            ->setDescription((string) $request->request->get('description'))
            ->setOriginalFilename($originalName)
            ->setStoredFilename($storedName)
            ->setMimeType($mimeType)
            ->setFileSize((int) $file->getSize());

        $entityManager->persist($document);
        $entityManager->flush();
        $this->addFlash('success', 'Document uploaded.');

        return $this->redirectToRoute('app_documents_index');
    }

    #[Route('/{id}/download', name: 'download', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function download(EmployeeDocument $document, KernelInterface $kernel): BinaryFileResponse
    {
        $this->denyUnlessDocumentVisible($document);
        $path = $this->getDocumentStorageDir($kernel).'/'.$document->getStoredFilename();

        if (!is_file($path)) {
            throw $this->createNotFoundException('Document file is missing.');
        }

        return $this->file($path, $document->getOriginalFilename(), ResponseHeaderBag::DISPOSITION_ATTACHMENT);
    }

    #[Route('/{id}/delete', name: 'delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(
        EmployeeDocument $document,
        Request $request,
        EntityManagerInterface $entityManager,
        KernelInterface $kernel,
    ): Response {
        $this->guardCsrf($request, 'document_delete_'.$document->getId());
        $this->denyUnlessDocumentEditable($document);

        $path = $this->getDocumentStorageDir($kernel).'/'.$document->getStoredFilename();
        if (is_file($path)) {
            unlink($path);
        }

        $entityManager->remove($document);
        $entityManager->flush();
        $this->addFlash('success', 'Document deleted.');

        return $this->redirectToRoute('app_documents_index');
    }

    private function getDocumentStorageDir(KernelInterface $kernel): string
    {
        $dir = $kernel->getProjectDir().'/var/documents';
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        return $dir;
    }

    private function denyUnlessDocumentVisible(EmployeeDocument $document): void
    {
        if ($this->isGranted(User::ROLE_SUPER_ADMIN)) {
            return;
        }

        $user = $this->getAuthenticatedUser();
        if (!$document->isCompanyWide() && $document->getOwner()?->getId() !== $user->getId()) {
            throw $this->createAccessDeniedException();
        }
    }

    private function denyUnlessDocumentEditable(EmployeeDocument $document): void
    {
        if ($this->isGranted(User::ROLE_SUPER_ADMIN)) {
            return;
        }

        if ($document->getOwner()?->getId() !== $this->getAuthenticatedUser()->getId()) {
            throw $this->createAccessDeniedException();
        }
    }

    private function getAuthenticatedUser(): User
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        return $user;
    }

    private function guardCsrf(Request $request, string $tokenId): void
    {
        if (!$this->isCsrfTokenValid($tokenId, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }
    }
}
