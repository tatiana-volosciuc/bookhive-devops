<?php

declare(strict_types=1);

namespace App\Service;

use League\Flysystem\FilesystemException;
use League\Flysystem\FilesystemOperator;
use Symfony\Component\DependencyInjection\Attribute\Target;

class ImageService
{
    public function __construct(
        #[Target('authors.storage')]
        private readonly FilesystemOperator $s3Storage,
    ) {

    }

    /**
     * @throws FilesystemException
     */
    public function upload(string $key, string $localFile): void
    {
        $stream = fopen($localFile, 'rb');

        if ($stream === false) {
            throw new \RuntimeException(sprintf(
                'Could not open image file "%s".',
                $localFile,
            ));
        }

        try {
            $this->s3Storage->writeStream($key, $stream);
        } finally {
            fclose($stream);
        }
    }

    /**
     * @throws FilesystemException
     */
    public function delete(string $key): void
    {
        if ($key === '') {
            return;
        }

        $this->s3Storage->delete($key);
    }

    /**
     * @return array<int, array{path: string, lastModified: int|null}>
     *
     * @throws FilesystemException
     */
    public function listFiles(string $prefix): array
    {
        $files = [];

        foreach ($this->s3Storage->listContents($prefix, true) as $item) {
            if (!$item->isFile()) {
                continue;
            }

            $files[] = [
                'path' => $item->path(),
                'lastModified' => $item->lastModified(),
            ];
        }

        return $files;
    }

    /**
     * @throws FilesystemException
     */
    public function exists(string $key): bool
    {
        return $this->s3Storage->fileExists($key);
    }

    /**
     * @return resource
     *
     * @throws FilesystemException
     */
    public function readStream(string $key)
    {
        return $this->s3Storage->readStream($key);
    }
}
