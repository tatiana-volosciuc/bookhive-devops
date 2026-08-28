<?php

namespace App\Service;

use App\Entity\Author;
use App\Entity\Book;
use App\Repository\AuthorRepository;
use App\Repository\CategoryRepository;
use App\Repository\PublisherRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class BookService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ValidatorInterface $validator,
        private CategoryRepository $categoryRepository,
        private PublisherRepository $publisherRepository,
        private AuthorRepository $authorRepository,
    ) {
    }

    /**
     * Validates the submitted book data.
     *
     * @return string[] List of human-readable error messages, empty if valid.
     */
    public function validateInput(array $data): array
    {
        $errors = [];

        if (empty($data['title'])) {
            $errors[] = 'Title is required.';
        }
        if (empty($data['isbn'])) {
            $errors[] = 'ISBN is required.';
        }
        if (empty($data['slug'])) {
            $errors[] = 'Slug is required.';
        }
        if ($data['price'] === null || $data['price'] === '') {
            $errors[] = 'Price is required.';
        }
        if ($data['stock'] === null || $data['stock'] === '') {
            $errors[] = 'Stock is required.';
        }
        if (empty($data['categoryId'])) {
            $errors[] = 'Category is required.';
        } elseif (!$this->categoryRepository->find($data['categoryId'])) {
            $errors[] = 'Selected category not found.';
        }

        return $errors;
    }

    /**
     * Creates and persists a new Book from submitted data.
     *
     * @return string[] Validation error messages; empty array means success.
     */
    public function createFromData(array $data): array
    {
        $inputErrors = $this->validateInput($data);
        if (!empty($inputErrors)) {
            return $inputErrors;
        }

        $book = new Book();
        $this->applyBasicFields($book, $data);
        $this->applyRelations($book, $data);

        $entityErrors = $this->validateEntity($book);
        if (!empty($entityErrors)) {
            return $entityErrors;
        }

        $this->entityManager->persist($book);
        $this->entityManager->flush();

        return [];
    }

    /**
     * Updates an existing Book from submitted data.
     *
     * @return string[] Validation error messages; empty array means success.
     */
    public function updateFromData(Book $book, array $data): array
    {
        $inputErrors = $this->validateInput($data);
        if (!empty($inputErrors)) {
            return $inputErrors;
        }

        $this->applyBasicFields($book, $data);
        $this->applyRelations($book, $data, resetAuthors: true);

        $entityErrors = $this->validateEntity($book);
        if (!empty($entityErrors)) {
            return $entityErrors;
        }

        $this->entityManager->flush();

        return [];
    }

    public function delete(Book $book): void
    {
        $this->entityManager->remove($book);
        $this->entityManager->flush();
    }

    private function applyBasicFields(Book $book, array $data): void
    {
        $book->setTitle($data['title']);
        $book->setIsbn($data['isbn']);
        $book->setSlug($data['slug']);
        $book->setDescription($data['description'] ?: null);
        $book->setPrice((float) $data['price']);
        $book->setStock((int) $data['stock']);
        $book->setPublishedAt(!empty($data['publishedAt']) ? new \DateTime($data['publishedAt']) : null);
    }

    private function applyRelations(Book $book, array $data, bool $resetAuthors = false): void
    {
        $category = $this->categoryRepository->find($data['categoryId']);
        $book->setCategory($category);

        $publisher = !empty($data['publisherId'])
            ? $this->publisherRepository->find($data['publisherId'])
            : null;
        $book->setPublisher($publisher);

        if ($resetAuthors) {
            foreach ($book->getAuthors()->toArray() as $existingAuthor) {
                $book->removeAuthor($existingAuthor);
            }
        }

        $authorIds = $data['authorIds'] ?? [];
        foreach ($authorIds as $authorId) {
            $author = $this->authorRepository->find($authorId);
            if ($author instanceof Author) {
                $book->addAuthor($author);
            }
        }
    }

    /**
     * @return string[]
     */
    private function validateEntity(Book $book): array
    {
        $violations = $this->validator->validate($book);
        if (count($violations) === 0) {
            return [];
        }

        $messages = [];
        foreach ($violations as $violation) {
            $messages[] = $violation->getMessage();
        }

        return $messages;
    }
}
