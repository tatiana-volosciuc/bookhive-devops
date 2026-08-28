<?php

namespace App\Service;

use App\Entity\Publisher;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class PublisherService
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
     * Creates and persists a new Publisher from submitted data.
     *
     * @return string[] Validation error messages; empty array means success.
     */
    public function createFromData(array $data): array
    {
        $inputErrors = $this->validateInput($data);
        if (!empty($inputErrors)) {
            return $inputErrors;
        }

        $publisher = new Publisher();
        $this->applyFields($publisher, $data);

        $entityErrors = $this->validateEntity($publisher);
        if (!empty($entityErrors)) {
            return $entityErrors;
        }

        $this->entityManager->persist($publisher);
        $this->entityManager->flush();

        return [];
    }

    /**
     * Updates an existing Publisher from submitted data.
     *
     * @return string[] Validation error messages; empty array means success.
     */
    public function updateFromData(Publisher $publisher, array $data): array
    {
        $inputErrors = $this->validateInput($data);
        if (!empty($inputErrors)) {
            return $inputErrors;
        }

        $this->applyFields($publisher, $data);

        $entityErrors = $this->validateEntity($publisher);
        if (!empty($entityErrors)) {
            return $entityErrors;
        }

        $this->entityManager->flush();

        return [];
    }

    public function delete(Publisher $publisher): void
    {
        $this->entityManager->remove($publisher);
        $this->entityManager->flush();
    }

    private function applyFields(Publisher $publisher, array $data): void
    {
        $publisher->setName($data['name']);
        $publisher->setWebsite($data['website'] ?: null);
        $publisher->setDescription($data['description'] ?: null);
    }

    /**
     * @return string[]
     */
    private function validateEntity(Publisher $publisher): array
    {
        $violations = $this->validator->validate($publisher);
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
