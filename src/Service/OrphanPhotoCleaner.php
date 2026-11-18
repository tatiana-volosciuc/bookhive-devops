<?php

declare(strict_types=1);

namespace App\Service;

use App\Repository\AuthorRepository;
use League\Flysystem\FilesystemException;
use Psr\Log\LoggerInterface;

final class OrphanPhotoCleaner
{
    private const PHOTO_PREFIX = 'authors/';

    public function __construct(
        private readonly ImageService $imageService,
        private readonly AuthorRepository $authorRepository,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Deletes author photos in storage that no Author references.
     * Files newer than $olderThan are kept: uploads happen before the DB
     * flush, so a fresh file may belong to a save that is still in progress.
     *
     * @return string[] Keys that were deleted (or would be, in a dry run).
     *
     * @throws FilesystemException
     */
    public function cleanup(\DateTimeImmutable $olderThan, bool $dryRun = false): array
    {
        $referenced = array_flip($this->authorRepository->findAllPhotoKeys());
        $cutoff = $olderThan->getTimestamp();
        $orphans = [];

        foreach ($this->imageService->listFiles(self::PHOTO_PREFIX) as $file) {
            if (isset($referenced[$file['path']])) {
                continue;
            }

            if ($file['lastModified'] === null || $file['lastModified'] >= $cutoff) {
                continue;
            }

            $orphans[] = $file['path'];
        }

        if ($dryRun) {
            return $orphans;
        }

        $deleted = [];
        foreach ($orphans as $key) {
            try {
                $this->imageService->delete($key);
                $deleted[] = $key;
                $this->logger->info('Deleted orphaned author photo.', ['photo_key' => $key]);
            } catch (\Throwable $exception) {
                $this->logger->warning('Failed to delete orphaned author photo.', [
                    'photo_key' => $key,
                    'exception' => $exception,
                ]);
            }
        }

        return $deleted;
    }
}
