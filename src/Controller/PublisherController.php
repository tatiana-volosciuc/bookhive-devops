<?php

namespace App\Controller;

use App\Repository\PublisherRepository;
use App\Service\PublisherService;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Twig\Environment;

class PublisherController
{
    public function __construct(
        private PublisherService $publisherService,
        private Environment $twig,
        private PublisherRepository $publisherRepository,
    ) {
    }

    #[Route('/publishers', name: 'publisher_list', methods: ['GET'])]
    public function list(): Response
    {
        return $this->render('publishers/publisher_list.html.twig', [
            'publishers' => $this->publisherRepository->findAll(),
        ]);
    }

    #[Route('/publishers/new', name: 'publisher_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            $data = $this->extractPublisherData($request);
            $errors = $this->publisherService->createFromData($data);

            if (!empty($errors)) {
                $this->flashErrors($request, $errors);
                return $this->render('publishers/publisher_new.html.twig', $data);
            }

            $this->flashSuccess($request, 'Publisher "' . $data['name'] . '" created successfully.');
            return new RedirectResponse('/publishers');
        }

        return $this->render('publishers/publisher_new.html.twig');
    }

    #[Route('/publishers/{id}/edit', name: 'publisher_edit', methods: ['GET', 'POST'])]
    public function edit(int $id, Request $request): Response
    {
        $publisher = $this->publisherRepository->find($id);

        if (!$publisher) {
            $this->flashError($request, 'Publisher not found.');
            return new RedirectResponse('/publishers');
        }

        if ($request->isMethod('POST')) {
            $data = $this->extractPublisherData($request);
            $errors = $this->publisherService->updateFromData($publisher, $data);

            if (!empty($errors)) {
                $this->flashErrors($request, $errors);
                return $this->render('publishers/publisher_edit.html.twig', array_merge($data, ['publisher' => $publisher]));
            }

            $this->flashSuccess($request, 'Publisher "' . $publisher->getName() . '" updated successfully.');
            return new RedirectResponse('/publishers');
        }

        return $this->render('publishers/publisher_edit.html.twig', ['publisher' => $publisher]);
    }

    #[Route('/publishers/{id}/delete', name: 'publisher_delete', methods: ['POST'])]
    public function delete(int $id, Request $request): RedirectResponse
    {
        $publisher = $this->publisherRepository->find($id);

        if (!$publisher) {
            $this->flashError($request, 'Publisher not found.');
            return new RedirectResponse('/publishers');
        }

        $name = $publisher->getName();
        $this->publisherService->delete($publisher);
        $this->flashSuccess($request, 'Publisher "' . $name . '" deleted.');

        return new RedirectResponse('/publishers');
    }

    /**
     * Pulls and normalizes publisher fields from the submitted request.
     */
    private function extractPublisherData(Request $request): array
    {
        return [
            'name' => $request->request->get('name'),
            'website' => $request->request->get('website'),
            'description' => $request->request->get('description'),
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
