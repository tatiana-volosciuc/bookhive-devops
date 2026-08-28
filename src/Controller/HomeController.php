<?php

namespace App\Controller;

use App\Repository\AuthorRepository;
use App\Repository\BookRepository;
use App\Repository\CategoryRepository;
use App\Repository\PublisherRepository;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Twig\Environment;

class HomeController
{
    public function __construct(
        private Environment $twig,
    ) {
    }

    #[Route('/', name: 'home', methods: ['GET'])]
    public function index(
        BookRepository $bookRepository,
        AuthorRepository $authorRepository,
        CategoryRepository $categoryRepository,
        PublisherRepository $publisherRepository,
    ): Response {
        return new Response($this->twig->render('home.html.twig', [
            'bookCount' => $bookRepository->count([]),
            'authorCount' => $authorRepository->count([]),
            'categoryCount' => $categoryRepository->count([]),
            'publisherCount' => $publisherRepository->count([]),
        ]));
    }
}
