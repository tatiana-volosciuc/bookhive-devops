<?php

namespace App\Service;

use App\Entity\Category;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class CategoryService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ValidatorInterface $validator,
    ) {
    }

    /**
     * @return string[] List of human-readable error messages, empty if valid.
     */
    public function validateInput(array $data): array
    {
        $errors = [];

        if (empty($data['name'])) {
            $errors[] = 'Name is required.';
        }
        if (empty($data['slug'])) {
            $errors[] = 'Slug is required.';
        }

        return $errors;
    }

    /**
     * Creates and persists a new Category from submitted data.
     *
     * @return string[] Validation error messages; empty array means success.
     */
    public function createFromData(array $data): array
    {
        $inputErrors = $this->validateInput($data);
        if (!empty($inputErrors)) {
            return $inputErrors;
        }

        $category = new Category();
        $this->applyFields($category, $data);

        $entityErrors = $this->validateEntity($category);
        if (!empty($entityErrors)) {
            return $entityErrors;
        }

        $this->entityManager->persist($category);
        $this->entityManager->flush();

        return [];
    }

    /**
     * Updates an existing Category from submitted data.
     *
     * @return string[] Validation error messages; empty array means success.
     */
    public function updateFromData(Category $category, array $data): array
    {
        $inputErrors = $this->validateInput($data);
        if (!empty($inputErrors)) {
            return $inputErrors;
        }

        $this->applyFields($category, $data);

        $entityErrors = $this->validateEntity($category);
        if (!empty($entityErrors)) {
            return $entityErrors;
        }

        $this->entityManager->flush();

        return [];
    }

    public function delete(Category $category): void
    {
        $this->entityManager->remove($category);
        $this->entityManager->flush();
    }

    private function applyFields(Category $category, array $data): void
    {
        $category->setName($data['name']);
        $category->setSlug($data['slug']);
    }

    /**
     * @return string[]
     */
    private function validateEntity(Category $category): array
    {
        $violations = $this->validator->validate($category);
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
