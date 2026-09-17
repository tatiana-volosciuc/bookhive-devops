<?php

namespace App\Service;

use App\Entity\Author;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class AuthorService
{
    private const ALLOWED_PHOTO_MIME_TYPES = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    private const MAX_PHOTO_SIZE_BYTES = 5 * 1024 * 1024;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private ValidatorInterface $validator,
        private Filesystem $filesystem,
        #[Autowire('%authors_photo_directory%')]
        private string $photoDirectory,
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
    public function createFromData(array $data, ?UploadedFile $photo = null): array
    {
        $inputErrors = $this->validateInput($data);
        if (!empty($inputErrors)) {
            return $inputErrors;
        }

        $photoErrors = $this->validatePhoto($photo);
        if (!empty($photoErrors)) {
            return $photoErrors;
        }

        $author = new Author();
        $this->applyFields($author, $data);

        $entityErrors = $this->validateEntity($author);
        if (!empty($entityErrors)) {
            return $entityErrors;
        }

        $this->handlePhotoUpload($author, $photo);

        $this->entityManager->persist($author);
        $this->entityManager->flush();

        return [];
    }

    /**
     * Updates an existing Author from submitted data.
     *
     * @return string[] Validation error messages; empty array means success.
     */
    public function updateFromData(Author $author, array $data, ?UploadedFile $photo = null): array
    {
        $inputErrors = $this->validateInput($data);
        if (!empty($inputErrors)) {
            return $inputErrors;
        }

        $photoErrors = $this->validatePhoto($photo);
        if (!empty($photoErrors)) {
            return $photoErrors;
        }

        $this->applyFields($author, $data);

        $entityErrors = $this->validateEntity($author);
        if (!empty($entityErrors)) {
            return $entityErrors;
        }

        $this->handlePhotoUpload($author, $photo);

        $this->entityManager->flush();

        return [];
    }

    public function delete(Author $author): void
    {
        $this->deletePhotoFile($author->getPhotoKey());
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
    private function validatePhoto(?UploadedFile $photo): array
    {
        if ($photo === null) {
            return [];
        }

        if (!$photo->isValid()) {
            return ['The uploaded photo could not be read. Please try again.'];
        }

        if ($photo->getSize() > self::MAX_PHOTO_SIZE_BYTES) {
            return ['Photo must be smaller than 5 MB.'];
        }

        if (!in_array($photo->getMimeType(), self::ALLOWED_PHOTO_MIME_TYPES, true)) {
            return ['Photo must be a JPEG, PNG, WEBP, or GIF image.'];
        }

        return [];
    }

    /**
     * Stores the uploaded photo on disk and points the author at it, replacing any previous photo.
     */
    private function handlePhotoUpload(Author $author, ?UploadedFile $photo): void
    {
        if ($photo === null) {
            return;
        }

        $this->filesystem->mkdir($this->photoDirectory);

        $newFilename = bin2hex(random_bytes(16)) . '.' . $photo->guessExtension();
        $photo->move($this->photoDirectory, $newFilename);

        $this->deletePhotoFile($author->getPhotoKey());
        $author->setPhotoKey($newFilename);
    }

    private function deletePhotoFile(?string $photoKey): void
    {
        if ($photoKey === null) {
            return;
        }

        $path = rtrim($this->photoDirectory, '/') . '/' . $photoKey;
        if ($this->filesystem->exists($path)) {
            $this->filesystem->remove($path);
        }
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
