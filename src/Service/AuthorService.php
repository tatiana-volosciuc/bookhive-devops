<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Author;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class AuthorService
{
    private const ALLOWED_PHOTO_MIME_TYPES = [
        'image/jpeg',
        'image/png',
        'image/webp',
        'image/gif',
    ];

    private const MAX_PHOTO_SIZE_BYTES = 5 * 1024 * 1024;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ValidatorInterface $validator,
        private readonly ImageService $imageService,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @return string[] List of human-readable error messages, empty if valid.
     */
    public function validateInput(array $data): array
    {
        $errors = [];

        if (
            !isset($data['name'])
            || !is_string($data['name'])
            || trim($data['name']) === ''
        ) {
            $errors[] = 'Name is required.';
        }

        return $errors;
    }

    /**
     * @return string[] Validation errors. An empty array means success.
     *
     * @throws FilesystemException
     */
    public function createFromData(
        array $data,
        ?UploadedFile $photo = null,
    ): array {
        $inputErrors = $this->validateInput($data);
        if ($inputErrors !== []) {
            return $inputErrors;
        }

        $photoErrors = $this->validatePhoto($photo);
        if ($photoErrors !== []) {
            return $photoErrors;
        }

        $author = new Author();
        $this->applyFields($author, $data);
        $entityErrors = $this->validateEntity($author);
        if ($entityErrors !== []) {
            return $entityErrors;
        }

        $newPhotoKey = null;
        try {
            if ($photo !== null) {
                $newPhotoKey = $this->uploadPhoto($photo);
                $author->setPhotoKey($newPhotoKey);
            }

            $this->entityManager->persist($author);
            $this->entityManager->flush();
        } catch (\Throwable $exception) {
            if ($newPhotoKey !== null) {
                $this->deletePhotoSafely($newPhotoKey);
            }
            throw $exception;
        }

        return [];
    }

    /**
     * Updates an existing Author from submitted data.
     *
     * @return string[] Validation error messages; empty array means success.
     *
     * @throws FilesystemException
     */
    public function updateFromData(
        Author $author,
        array $data,
        ?UploadedFile $photo = null,
    ): array {
        $inputErrors = $this->validateInput($data);
        if ($inputErrors !== []) {
            return $inputErrors;
        }

        $photoErrors = $this->validatePhoto($photo);
        if ($photoErrors !== []) {
            return $photoErrors;
        }

        $this->applyFields($author, $data);

        $entityErrors = $this->validateEntity($author);
        if ($entityErrors !== []) {
            return $entityErrors;
        }

        if ($photo === null) {
            $this->entityManager->flush();

            return [];
        }

        $oldPhotoKey = $author->getPhotoKey();
        $newPhotoKey = $this->uploadPhoto($photo);
        $author->setPhotoKey($newPhotoKey);

        try {
            $this->entityManager->flush();
        } catch (\Throwable $exception) {
            $author->setPhotoKey($oldPhotoKey);
            $this->deletePhotoSafely($newPhotoKey);

            throw $exception;
        }

        if ($oldPhotoKey !== null && $oldPhotoKey !== $newPhotoKey) {
            $this->deletePhotoSafely($oldPhotoKey);
        }

        return [];
    }

    /**
     * @throws FilesystemException
     */
    public function delete(Author $author): void
    {
        $photoKey = $author->getPhotoKey();

        $this->entityManager->remove($author);
        $this->entityManager->flush();

        if ($photoKey !== null && $photoKey !== '') {
            $this->deletePhotoSafely($photoKey);
        }
    }

    private function applyFields(Author $author, array $data): void
    {
        $author->setName(trim((string) $data['name']));
        $bio = isset($data['bio'])
            ? trim((string) $data['bio'])
            : '';

        $author->setBio($bio !== '' ? $bio : null);
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

        $size = $photo->getSize();
        if ($size === false || $size > self::MAX_PHOTO_SIZE_BYTES) {
            return ['Photo must be smaller than 5 MB.'];
        }

        $mimeType = $photo->getMimeType();
        if (
            $mimeType === null
            || !in_array($mimeType, self::ALLOWED_PHOTO_MIME_TYPES, true)
        ) {
            return ['Photo must be a JPEG, PNG, WEBP, or GIF image.'];
        }

        return [];
    }

    /**
     * Uploads the photo and returns its S3 object key.
     *
     * @throws FilesystemException
     */
    private function uploadPhoto(UploadedFile $photo): string
    {
        $extension = $photo->guessExtension() ?: 'bin';

        $photoKey = sprintf(
            'authors/%s.%s',
            bin2hex(random_bytes(16)),
            $extension,
        );

        $this->imageService->upload(
            $photoKey,
            $photo->getPathname(),
        );

        return $photoKey;
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

    /**
     * Best-effort cleanup used during rollback, so a deletion failure never masks the original exception.
     */
    private function deletePhotoSafely(string $key): void
    {
        try {
            $this->imageService->delete($key);
        } catch (\Throwable $exception) {
            $this->logger->warning('Failed to delete author photo from storage.', [
                'photo_key' => $key,
                'exception' => $exception,
            ]);
        }
    }
}
