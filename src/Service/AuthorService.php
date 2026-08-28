<?php

namespace App\Service;

use App\Entity\Author;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class AuthorService
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

        return $errors;
    }

    /**
     * Creates and persists a new Author from submitted data.
     *
     * @return string[] Validation error messages; empty array means success.
     */
    public function createFromData(array $data): array
    {
        $inputErrors = $this->validateInput($data);
        if (!empty($inputErrors)) {
            return $inputErrors;
        }

        $author = new Author();
        $this->applyFields($author, $data);

        $entityErrors = $this->validateEntity($author);
        if (!empty($entityErrors)) {
            return $entityErrors;
        }

        $this->entityManager->persist($author);
        $this->entityManager->flush();

        return [];
    }

    /**
     * Updates an existing Author from submitted data.
     *
     * @return string[] Validation error messages; empty array means success.
     */
    public function updateFromData(Author $author, array $data): array
    {
        $inputErrors = $this->validateInput($data);
        if (!empty($inputErrors)) {
            return $inputErrors;
        }

        $this->applyFields($author, $data);

        $entityErrors = $this->validateEntity($author);
        if (!empty($entityErrors)) {
            return $entityErrors;
        }

        $this->entityManager->flush();

        return [];
    }

    public function delete(Author $author): void
    {
        $this->entityManager->remove($author);
        $this->entityManager->flush();
    }

    private function applyFields(Author $author, array $data): void
    {
        $author->setName($data['name']);
        $author->setBio($data['bio'] ?: null);
    }

    /**
     * @return string[]
     */
    private function validateEntity(Author $author): array
    {
        $violations = $this->validator->validate($author);
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
