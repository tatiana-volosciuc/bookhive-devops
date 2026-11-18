<?php

namespace App\Tests\Unit\Service;

use App\Repository\AuthorRepository;
use App\Service\ImageService;
use App\Service\OrphanPhotoCleaner;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class OrphanPhotoCleanerTest extends TestCase
{
    private ImageService&MockObject $imageService;
    private OrphanPhotoCleaner $cleaner;
    private \DateTimeImmutable $cutoff;

    protected function setUp(): void
    {
        $this->cutoff = new \DateTimeImmutable('2026-01-10 12:00:00');
        $old = $this->cutoff->modify('-2 days')->getTimestamp();
        $recent = $this->cutoff->modify('+1 hour')->getTimestamp();

        $authorRepository = $this->createStub(AuthorRepository::class);
        $authorRepository->method('findAllPhotoKeys')->willReturn(['authors/used.jpg']);

        $this->imageService = $this->createMock(ImageService::class);
        $this->imageService->method('listFiles')->with('authors/')->willReturn([
            ['path' => 'authors/used.jpg', 'lastModified' => $old],
            ['path' => 'authors/orphan-old.jpg', 'lastModified' => $old],
            ['path' => 'authors/orphan-new.jpg', 'lastModified' => $recent],
        ]);

        $this->cleaner = new OrphanPhotoCleaner(
            $this->imageService,
            $authorRepository,
            $this->createStub(LoggerInterface::class),
        );
    }

    public function testDeletesOnlyOldUnreferencedPhotos(): void
    {
        $this->imageService->expects($this->once())
            ->method('delete')
            ->with('authors/orphan-old.jpg');

        $this->assertSame(['authors/orphan-old.jpg'], $this->cleaner->cleanup($this->cutoff));
    }

    public function testDryRunReportsWithoutDeleting(): void
    {
        $this->imageService->expects($this->never())->method('delete');

        $this->assertSame(['authors/orphan-old.jpg'], $this->cleaner->cleanup($this->cutoff, true));
    }
}
