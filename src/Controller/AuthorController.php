<?php

namespace App\Controller;

use App\Repository\AuthorRepository;
use App\Service\AuthorService;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Twig\Environment;

class AuthorController
{
    public function __construct(
        private AuthorService $authorService,
        private Environment $twig,
        private AuthorRepository $authorRepository,
    ) {
    }

    #[Route('/authors', name: 'author_list', methods: ['GET'])]
    public function list(): Response
    {
        return $this->render('authors/author_list.html.twig', [
            'authors' => $this->authorRepository->findAll(),
        ]);
    }

    #[Route('/authors/new', name: 'author_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            $data = $this->extractAuthorData($request);
            $errors = $this->authorService->createFromData($data);

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
            $errors = $this->authorService->updateFromData($author, $data);

            if (!empty($errors)) {
                $this->flashErrors($request, $errors);
                return $this->render('authors/author_edit.html.twig', array_merge($data, ['author' => $author]));
            }

            $this->flashSuccess($request, 'Author "' . $author->getName() . '" updated successfully.');
            return new RedirectResponse('/authors');
        }

        return $this->render('authors/author_edit.html.twig', ['author' => $author]);
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
