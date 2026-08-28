<?php

namespace App\Controller;

use App\Repository\AuthorRepository;
use App\Repository\BookRepository;
use App\Repository\CategoryRepository;
use App\Repository\PublisherRepository;
use App\Service\BookService;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Twig\Environment;

class BookController
{
    public function __construct(
        private BookService $bookService,
        private Environment $twig,
        private BookRepository $bookRepository,
        private CategoryRepository $categoryRepository,
        private PublisherRepository $publisherRepository,
        private AuthorRepository $authorRepository,
    ) {
    }

    #[Route('/books', name: 'book_list', methods: ['GET'])]
    public function list(): Response
    {
        return $this->render('books/book_list.html.twig', [
            'books' => $this->bookRepository->findAll(),
        ]);
    }

    #[Route('/books/new', name: 'book_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            $data = $this->extractBookData($request);
            $errors = $this->bookService->createFromData($data);

            if (!empty($errors)) {
                $this->flashErrors($request, $errors);
                return $this->render('books/book_new.html.twig', $this->formViewData($data));
            }

            $this->flashSuccess($request, 'Book "' . $data['title'] . '" created successfully.');
            return new RedirectResponse('/books');
        }

        return $this->render('books/book_new.html.twig', $this->relationOptions());
    }

    #[Route('/books/{id}/edit', name: 'book_edit', methods: ['GET', 'POST'])]
    public function edit(int $id, Request $request): Response
    {
        $book = $this->bookRepository->find($id);

        if (!$book) {
            $this->flashError($request, 'Book not found.');
            return new RedirectResponse('/books');
        }

        if ($request->isMethod('POST')) {
            $data = $this->extractBookData($request);
            $errors = $this->bookService->updateFromData($book, $data);

            if (!empty($errors)) {
                $this->flashErrors($request, $errors);
                return $this->render('books/book_edit.html.twig', $this->formViewData($data, $book));
            }

            $this->flashSuccess($request, 'Book "' . $book->getTitle() . '" updated successfully.');
            return new RedirectResponse('/books');
        }

        return $this->render('books/book_edit.html.twig', array_merge(
            ['book' => $book],
            $this->relationOptions()
        ));
    }

    #[Route('/books/{id}/delete', name: 'book_delete', methods: ['POST'])]
    public function delete(int $id, Request $request): RedirectResponse
    {
        $book = $this->bookRepository->find($id);

        if (!$book) {
            $this->flashError($request, 'Book not found.');
            return new RedirectResponse('/books');
        }

        $title = $book->getTitle();
        $this->bookService->delete($book);
        $this->flashSuccess($request, 'Book "' . $title . '" deleted.');

        return new RedirectResponse('/books');
    }

    /**
     * Pulls and normalizes book fields from the submitted request.
     */
    private function extractBookData(Request $request): array
    {
        return [
            'title' => $request->request->get('title'),
            'isbn' => $request->request->get('isbn'),
            'slug' => $request->request->get('slug'),
            'description' => $request->request->get('description'),
            'price' => $request->request->get('price'),
            'stock' => $request->request->get('stock'),
            'publishedAt' => $request->request->get('publishedAt'),
            'categoryId' => (int) $request->request->get('categoryId') ?: null,
            'publisherId' => (int) $request->request->get('publisherId') ?: null,
            'authorIds' => array_map('intval', $request->request->all('authorIds')),
        ];
    }

    /**
     * Builds the variable set needed to re-render a form after validation failure.
     */
    private function formViewData(array $data, ?object $book = null): array
    {
        $view = array_merge($data, $this->relationOptions());

        if ($book !== null) {
            $view['book'] = $book;
        }

        return $view;
    }

    /**
     * Fetches dropdown/multi-select options shared by the new/edit forms.
     */
    private function relationOptions(): array
    {
        return [
            'categories' => $this->categoryRepository->findAll(),
            'publishers' => $this->publisherRepository->findAll(),
            'authors' => $this->authorRepository->findAll(),
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
