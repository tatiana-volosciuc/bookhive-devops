<?php

namespace App\Controller;

use App\Repository\AuthorRepository;
use App\Service\AuthorService;
use App\Service\ImageService;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Mime\MimeTypes;
use Symfony\Component\Routing\Attribute\Route;
use Psr\Log\LoggerInterface;
use Twig\Environment;

class AuthorController
{
    public function __construct(
        private AuthorService $authorService,
        private ImageService $imageService,
        private Environment $twig,
        private AuthorRepository $authorRepository,
        private LoggerInterface $logger,
    ) {
    }

    #[Route('/authors', name: 'author_list', methods: ['GET'])]
    public function list(): Response
    {
        $this->logger->info('Monolog test: this is an info message');

        return $this->render('authors/author_list.html.twig', [
            'authors' => $this->authorRepository->findAll(),
        ]);
    }

    #[Route('/authors/new', name: 'author_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            $data = $this->extractAuthorData($request);
            $photo = $request->files->get('photo');
            $errors = $this->authorService->createFromData($data, $photo);

            if (!empty($errors)) {
                $this->flashErrors($request, $errors);
                return $this->render('authors/author_new.html.twig', $data);
            }

            $this->flashSuccess($request, 'Author "' . $data['name'] . '" created successfully.');
            return new RedirectResponse('/authors');
        }

        return $this->render('authors/author_new.html.twig');
    }

    #[Route('/authors/{id}/edit', name: 'author_edit', methods: ['GET', 'POST'])]
    public function edit(int $id, Request $request): Response
    {
        $author = $this->authorRepository->find($id);

        if (!$author) {
            $this->flashError($request, 'Author not found.');
            return new RedirectResponse('/authors');
        }

        if ($request->isMethod('POST')) {
            $data = $this->extractAuthorData($request);
            $photo = $request->files->get('photo');
            $errors = $this->authorService->updateFromData($author, $data, $photo);

            if (!empty($errors)) {
                $this->flashErrors($request, $errors);
                return $this->render('authors/author_edit.html.twig', array_merge($data, ['author' => $author]));
            }

            $this->flashSuccess($request, 'Author "' . $author->getName() . '" updated successfully.');
            return new RedirectResponse('/authors');
        }

        return $this->render('authors/author_edit.html.twig', ['author' => $author]);
    }

    #[Route('/authors/{id}/photo', name: 'author_photo', methods: ['GET'])]
    public function photo(int $id): Response
    {
        $author = $this->authorRepository->find($id);
        $photoKey = $author?->getPhotoKey();

        if ($photoKey === null || !$this->imageService->exists($photoKey)) {
            throw new NotFoundHttpException('Photo not found.');
        }

        $extension = pathinfo($photoKey, PATHINFO_EXTENSION);
        $mimeType = MimeTypes::getDefault()->getMimeTypes($extension)[0] ?? 'application/octet-stream';

        $response = new StreamedResponse(function () use ($photoKey): void {
            $stream = $this->imageService->readStream($photoKey);
            fpassthru($stream);
            fclose($stream);
        });
        $response->headers->set('Content-Type', $mimeType);

        return $response;
    }

    #[Route('/authors/{id}/delete', name: 'author_delete', methods: ['POST'])]
    public function delete(int $id, Request $request): RedirectResponse
    {
        $author = $this->authorRepository->find($id);

        if (!$author) {
            $this->flashError($request, 'Author not found.');
            return new RedirectResponse('/authors');
        }

        $name = $author->getName();
        $this->authorService->delete($author);
        $this->flashSuccess($request, 'Author "' . $name . '" deleted.');

        return new RedirectResponse('/authors');
    }

    /**
     * Pulls and normalizes author fields from the submitted request.
     */
    private function extractAuthorData(Request $request): array
    {
        return [
            'name' => $request->request->get('name'),
            'bio' => $request->request->get('bio'),
        ];
    }

    private function render(string $template, array $context = []): Response
    {
        return new Response($this->twig->render($template, $context));
    }

    private function flashSuccess(Request $request, string $message): void
    {
        $request->getSession()->getFlashBag()->add('success', $message);
    }

    private function flashError(Request $request, string $message): void
    {
        $request->getSession()->getFlashBag()->add('error', $message);
    }

    /**
     * @param string[] $messages
     */
    private function flashErrors(Request $request, array $messages): void
    {
        foreach ($messages as $message) {
            $this->flashError($request, $message);
        }
    }
}
