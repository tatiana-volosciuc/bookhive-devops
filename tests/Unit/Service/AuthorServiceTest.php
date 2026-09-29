<?php

namespace App\Tests\Unit\Service;

use App\Entity\Author;
use App\Service\AuthorService;
use App\Service\ImageService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class AuthorServiceTest extends TestCase
{
    private EntityManagerInterface&MockObject $entityManager;
    private ValidatorInterface&Stub $validator;
    private ImageService&MockObject $imageService;
    private AuthorService $service;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->validator = $this->createStub(ValidatorInterface::class);
        $this->imageService = $this->createMock(ImageService::class);

        $this->service = new AuthorService($this->entityManager, $this->validator, $this->imageService);
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testValidateInputReturnsErrorWhenNameMissing(): void
    {
        $errors = $this->service->validateInput(['name' => '', 'bio' => 'Some bio']);

        $this->assertCount(1, $errors);
        $this->assertSame('Name is required.', $errors[0]);
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testValidateInputReturnsNoErrorsWhenNamePresent(): void
    {
        $errors = $this->service->validateInput(['name' => 'Jane Austen', 'bio' => 'Some bio']);

        $this->assertSame([], $errors);
    }

    public function testCreateFromDataReturnsErrorsWithoutPersistingWhenNameMissing(): void
    {
        $this->entityManager->expects($this->never())->method('persist');
        $this->entityManager->expects($this->never())->method('flush');

        $errors = $this->service->createFromData(['name' => '', 'bio' => 'Some bio']);

        $this->assertSame(['Name is required.'], $errors);
    }

    public function testCreateFromDataReturnsEntityValidationErrorsWithoutPersisting(): void
    {
        $violation = new ConstraintViolation(
            'Bio is too long.',
            null,
            [],
            '',
            'bio',
            'some very long bio'
        );

        $this->validator
            ->method('validate')
            ->willReturn(new ConstraintViolationList([$violation]));

        $this->entityManager->expects($this->never())->method('persist');
        $this->entityManager->expects($this->never())->method('flush');

        $errors = $this->service->createFromData([
            'name' => 'Jane Austen',
            'bio' => 'some very long bio',
        ]);

        $this->assertSame(['Bio is too long.'], $errors);
    }

    public function testCreateFromDataPersistsAndFlushesOnValidData(): void
    {
        $this->validator
            ->method('validate')
            ->willReturn(new ConstraintViolationList());

        $this->entityManager
            ->expects($this->once())
            ->method('persist')
            ->with($this->isInstanceOf(Author::class));

        $this->entityManager->expects($this->once())->method('flush');

        $errors = $this->service->createFromData([
            'name' => 'Jane Austen',
            'bio' => 'English novelist.',
        ]);

        $this->assertSame([], $errors);
    }

    public function testCreateFromDataReturnsPhotoSizeValidationErrorWithoutPersisting(): void
    {
        $photo = $this->createMock(UploadedFile::class);
        $photo->method('isValid')->willReturn(true);
        $photo->method('getSize')->willReturn((5 * 1024 * 1024) + 1);

        $this->entityManager->expects($this->never())->method('persist');
        $this->entityManager->expects($this->never())->method('flush');

        $errors = $this->service->createFromData([
            'name' => 'Jane Austen',
            'bio' => 'English novelist.',
        ], $photo);

        $this->assertSame(['Photo must be smaller than 5 MB.'], $errors);
    }

    public function testCreateFromDataReturnsPhotoMimeValidationErrorWithoutPersisting(): void
    {
        $photo = $this->createMock(UploadedFile::class);
        $photo->method('isValid')->willReturn(true);
        $photo->method('getSize')->willReturn(1024);
        $photo->method('getMimeType')->willReturn('application/pdf');

        $this->entityManager->expects($this->never())->method('persist');
        $this->entityManager->expects($this->never())->method('flush');

        $errors = $this->service->createFromData([
            'name' => 'Jane Austen',
            'bio' => 'English novelist.',
        ], $photo);

        $this->assertSame(['Photo must be a JPEG, PNG, WEBP, or GIF image.'], $errors);
    }

    public function testCreateFromDataUploadsPhotoAndStoresPhotoKey(): void
    {
        $this->validator
            ->method('validate')
            ->willReturn(new ConstraintViolationList());

        $photo = $this->createMock(UploadedFile::class);
        $photo->method('isValid')->willReturn(true);
        $photo->method('getSize')->willReturn(2048);
        $photo->method('getMimeType')->willReturn('image/png');
        $photo->method('guessExtension')->willReturn('png');
        $photo->method('getPathname')->willReturn('/tmp/uploaded-photo.png');

        $this->imageService
            ->expects($this->once())
            ->method('upload')
            ->with(
                $this->callback(static fn (string $key): bool => str_starts_with($key, 'authors/') && str_ends_with($key, '.png')),
                '/tmp/uploaded-photo.png',
            );

        $capturedAuthor = null;
        $this->entityManager
            ->expects($this->once())
            ->method('persist')
            ->willReturnCallback(function ($entity) use (&$capturedAuthor): void {
                $capturedAuthor = $entity;
            });
        $this->entityManager->expects($this->once())->method('flush');

        $errors = $this->service->createFromData([
            'name' => 'Jane Austen',
            'bio' => 'English novelist.',
        ], $photo);

        $this->assertSame([], $errors);
        $this->assertInstanceOf(Author::class, $capturedAuthor);
        $this->assertNotNull($capturedAuthor->getPhotoKey());
        $this->assertStringEndsWith('.png', $capturedAuthor->getPhotoKey());
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testCreateFromDataSetsFieldsOnNewAuthor(): void
    {
        $this->validator
            ->method('validate')
            ->willReturn(new ConstraintViolationList());

        $capturedAuthor = null;
        $this->entityManager
            ->method('persist')
            ->willReturnCallback(function ($entity) use (&$capturedAuthor) {
                $capturedAuthor = $entity;
            });

        $this->service->createFromData([
            'name' => 'George Orwell',
            'bio' => 'English novelist and essayist.',
        ]);

        $this->assertInstanceOf(Author::class, $capturedAuthor);
        $this->assertSame('George Orwell', $capturedAuthor->getName());
        $this->assertSame('English novelist and essayist.', $capturedAuthor->getBio());
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testCreateFromDataSetsBioToNullWhenEmpty(): void
    {
        $this->validator
            ->method('validate')
            ->willReturn(new ConstraintViolationList());

        $capturedAuthor = null;
        $this->entityManager
            ->method('persist')
            ->willReturnCallback(function ($entity) use (&$capturedAuthor) {
                $capturedAuthor = $entity;
            });

        $this->service->createFromData([
            'name' => 'George Orwell',
            'bio' => '',
        ]);

        $this->assertNull($capturedAuthor->getBio());
    }

    public function testUpdateFromDataReturnsErrorsWithoutFlushingWhenNameMissing(): void
    {
        $author = new Author();
        $author->setName('Original Name');

        $this->entityManager->expects($this->never())->method('flush');

        $errors = $this->service->updateFromData($author, ['name' => '', 'bio' => 'x']);

        $this->assertSame(['Name is required.'], $errors);
        $this->assertSame('Original Name', $author->getName()); // unchanged
    }

    public function testUpdateFromDataReturnsEntityValidationErrorsWithoutFlushing(): void
    {
        $author = new Author();
        $author->setName('Original Name');

        $violation = new ConstraintViolation(
            'Bio is too long.',
            null,
            [],
            '',
            'bio',
            'a bio'
        );

        $this->validator
            ->method('validate')
            ->willReturn(new ConstraintViolationList([$violation]));

        $this->entityManager->expects($this->never())->method('flush');

        $errors = $this->service->updateFromData($author, [
            'name' => 'Updated Name',
            'bio' => 'a bio',
        ]);

        $this->assertSame(['Bio is too long.'], $errors);
    }

    public function testUpdateFromDataAppliesFieldsAndFlushesOnValidData(): void
    {
        $author = new Author();
        $author->setName('Original Name');
        $author->setBio('Original bio.');

        $this->validator
            ->method('validate')
            ->willReturn(new ConstraintViolationList());

        $this->entityManager->expects($this->once())->method('flush');
        $this->entityManager->expects($this->never())->method('persist');

        $errors = $this->service->updateFromData($author, [
            'name' => 'Updated Name',
            'bio' => 'Updated bio.',
        ]);

        $this->assertSame([], $errors);
        $this->assertSame('Updated Name', $author->getName());
        $this->assertSame('Updated bio.', $author->getBio());
    }

    public function testUpdateFromDataReplacesPhotoAndDeletesPreviousStoredFile(): void
    {
        $author = new Author();
        $author->setName('Original Name');
        $author->setPhotoKey('authors/old-photo.png');

        $this->validator
            ->method('validate')
            ->willReturn(new ConstraintViolationList());

        $photo = $this->createMock(UploadedFile::class);
        $photo->method('isValid')->willReturn(true);
        $photo->method('getSize')->willReturn(2048);
        $photo->method('getMimeType')->willReturn('image/jpeg');
        $photo->method('guessExtension')->willReturn('jpg');
        $photo->method('getPathname')->willReturn('/tmp/uploaded-photo.jpg');

        $this->imageService
            ->expects($this->once())
            ->method('upload')
            ->with(
                $this->callback(static fn (string $key): bool => str_ends_with($key, '.jpg')),
                '/tmp/uploaded-photo.jpg',
            );

        $this->imageService
            ->expects($this->once())
            ->method('delete')
            ->with('authors/old-photo.png');

        $this->entityManager->expects($this->once())->method('flush');

        $errors = $this->service->updateFromData($author, [
            'name' => 'Updated Name',
            'bio' => 'Updated bio.',
        ], $photo);

        $this->assertSame([], $errors);
        $this->assertNotSame('authors/old-photo.png', $author->getPhotoKey());
        $this->assertStringEndsWith('.jpg', $author->getPhotoKey());
    }

    public function testDeleteRemovesAndFlushesAuthor(): void
    {
        $author = new Author();
        $author->setName('To Be Deleted');

        $this->entityManager
            ->expects($this->once())
            ->method('remove')
            ->with($author);

        $this->entityManager->expects($this->once())->method('flush');

        $this->service->delete($author);
    }

    public function testDeleteRemovesStoredPhotoWhenPresent(): void
    {
        $author = new Author();
        $author->setName('To Be Deleted');
        $author->setPhotoKey('authors/author-photo.png');

        $this->imageService
            ->expects($this->once())
            ->method('delete')
            ->with('authors/author-photo.png');

        $this->entityManager->expects($this->once())->method('remove')->with($author);
        $this->entityManager->expects($this->once())->method('flush');

        $this->service->delete($author);
    }
}
