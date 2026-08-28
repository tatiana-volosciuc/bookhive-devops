<?php

namespace App\Controller;

use App\Repository\CategoryRepository;
use App\Service\CategoryService;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Twig\Environment;

class CategoryController
{
    public function __construct(
        private CategoryService $categoryService,
        private Environment $twig,
        private CategoryRepository $categoryRepository,
    ) {
    }

    #[Route('/categories', name: 'category_list', methods: ['GET'])]
    public function list(): Response
    {
        return $this->render('categories/category_list.html.twig', [
            'categories' => $this->categoryRepository->findAll(),
        ]);
    }

    #[Route('/categories/new', name: 'category_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            $data = $this->extractCategoryData($request);
            $errors = $this->categoryService->createFromData($data);

            if (!empty($errors)) {
                $this->flashErrors($request, $errors);
                return $this->render('categories/category_new.html.twig', $data);
            }

            $this->flashSuccess($request, 'Category "' . $data['name'] . '" created successfully.');
            return new RedirectResponse('/categories');
        }

        return $this->render('categories/category_new.html.twig');
    }

    #[Route('/categories/{id}/edit', name: 'category_edit', methods: ['GET', 'POST'])]
    public function edit(int $id, Request $request): Response
    {
        $category = $this->categoryRepository->find($id);

        if (!$category) {
            $this->flashError($request, 'Category not found.');
            return new RedirectResponse('/categories');
        }

        if ($request->isMethod('POST')) {
            $data = $this->extractCategoryData($request);
            $errors = $this->categoryService->updateFromData($category, $data);

            if (!empty($errors)) {
                $this->flashErrors($request, $errors);
                return $this->render('categories/category_edit.html.twig', array_merge($data, ['category' => $category]));
            }

            $this->flashSuccess($request, 'Category "' . $category->getName() . '" updated successfully.');
            return new RedirectResponse('/categories');
        }

        return $this->render('categories/category_edit.html.twig', ['category' => $category]);
    }

    #[Route('/categories/{id}/delete', name: 'category_delete', methods: ['POST'])]
    public function delete(int $id, Request $request): RedirectResponse
    {
        $category = $this->categoryRepository->find($id);

        if (!$category) {
            $this->flashError($request, 'Category not found.');
            return new RedirectResponse('/categories');
        }

        $name = $category->getName();
        $this->categoryService->delete($category);
        $this->flashSuccess($request, 'Category "' . $name . '" deleted.');

        return new RedirectResponse('/categories');
    }

    /**
     * Pulls and normalizes category fields from the submitted request.
     */
    private function extractCategoryData(Request $request): array
    {
        return [
            'name' => $request->request->get('name'),
            'slug' => $request->request->get('slug'),
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
