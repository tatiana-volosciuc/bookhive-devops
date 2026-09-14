<?php

namespace App\Tests\Unit\Service;

use App\Entity\Author;
use App\Entity\Book;
use App\Entity\Category;
use App\Entity\Publisher;
use App\Repository\AuthorRepository;
use App\Repository\CategoryRepository;
use App\Repository\PublisherRepository;
use App\Service\BookService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class BookServiceTest extends TestCase
{
    private EntityManagerInterface&MockObject $entityManager;
    private ValidatorInterface&Stub $validator;
    private CategoryRepository&MockObject $categoryRepository;
    private PublisherRepository&MockObject $publisherRepository;
    private AuthorRepository&MockObject $authorRepository;
    private BookService $service;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->validator = $this->createStub(ValidatorInterface::class);
        $this->categoryRepository = $this->createMock(CategoryRepository::class);
        $this->publisherRepository = $this->createMock(PublisherRepository::class);
        $this->authorRepository = $this->createMock(AuthorRepository::class);

        $this->service = new BookService(
            $this->entityManager,
            $this->validator,
            $this->categoryRepository,
            $this->publisherRepository,
            $this->authorRepository,
        );
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testValidateInputReturnsErrorWhenTitleMissing(): void
    {
        $this->categoryRepository->method('find')->willReturn(new Category());

        $errors = $this->service->validateInput($this->validBookData(['title' => '']));

        $this->assertContains('Title is required.', $errors);
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testValidateInputReturnsErrorWhenIsbnMissing(): void
    {
        $this->categoryRepository->method('find')->willReturn(new Category());

        $errors = $this->service->validateInput($this->validBookData(['isbn' => '']));

        $this->assertContains('ISBN is required.', $errors);
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testValidateInputReturnsErrorWhenSlugMissing(): void
    {
        $this->categoryRepository->method('find')->willReturn(new Category());

        $errors = $this->service->validateInput($this->validBookData(['slug' => '']));

        $this->assertContains('Slug is required.', $errors);
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testValidateInputReturnsErrorWhenPriceMissing(): void
    {
        $this->categoryRepository->method('find')->willReturn(new Category());

        $errors = $this->service->validateInput($this->validBookData(['price' => '']));

        $this->assertContains('Price is required.', $errors);
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testValidateInputReturnsErrorWhenStockMissing(): void
    {
        $this->categoryRepository->method('find')->willReturn(new Category());

        $errors = $this->service->validateInput($this->validBookData(['stock' => '']));

        $this->assertContains('Stock is required.', $errors);
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testValidateInputReturnsErrorWhenCategoryIdMissing(): void
    {
        $this->categoryRepository->expects($this->never())->method('find');

        $errors = $this->service->validateInput($this->validBookData(['categoryId' => null]));

        $this->assertContains('Category is required.', $errors);
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testValidateInputReturnsErrorWhenCategoryNotFound(): void
    {
        $this->categoryRepository->expects($this->once())->method('find')->with(1)->willReturn(null);

        $errors = $this->service->validateInput($this->validBookData(['categoryId' => 1]));

        $this->assertContains('Selected category not found.', $errors);
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testValidateInputReturnsNoErrorsWhenDataValid(): void
    {
        $this->categoryRepository->expects($this->once())->method('find')->with(1)->willReturn(new Category());

        $errors = $this->service->validateInput($this->validBookData());

        $this->assertSame([], $errors);
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testCreateFromDataReturnsErrorsWithoutPersistingWhenTitleMissing(): void
    {
        $this->entityManager->expects($this->never())->method('persist');
        $this->entityManager->expects($this->never())->method('flush');

        $errors = $this->service->createFromData($this->validBookData(['title' => '', 'categoryId' => null]));

        $this->assertContains('Title is required.', $errors);
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testCreateFromDataReturnsEntityValidationErrorsWithoutPersisting(): void
    {
        $this->categoryRepository->method('find')->willReturn(new Category());

        $violation = new ConstraintViolation(
            'ISBN format is invalid.',
            null,
            [],
            '',
            'isbn',
            'bad-isbn'
        );

        $this->validator
            ->method('validate')
            ->willReturn(new ConstraintViolationList([$violation]));

        $this->entityManager->expects($this->never())->method('persist');
        $this->entityManager->expects($this->never())->method('flush');

        $errors = $this->service->createFromData($this->validBookData(['isbn' => 'bad-isbn']));

        $this->assertSame(['ISBN format is invalid.'], $errors);
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testCreateFromDataPersistsAndFlushesOnValidData(): void
    {
        $category = new Category();
        $this->categoryRepository->method('find')->willReturn($category);

        $this->validator
            ->method('validate')
            ->willReturn(new ConstraintViolationList());

        $this->entityManager
            ->expects($this->once())
            ->method('persist')
            ->with($this->isInstanceOf(Book::class));

        $this->entityManager->expects($this->once())->method('flush');

        $errors = $this->service->createFromData($this->validBookData());

        $this->assertSame([], $errors);
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testCreateFromDataSetsBasicFieldsOnNewBook(): void
    {
        $category = new Category();
        $this->categoryRepository->method('find')->willReturn($category);
        $this->validator->method('validate')->willReturn(new ConstraintViolationList());

        $capturedBook = null;
        $this->entityManager
            ->method('persist')
            ->willReturnCallback(function ($entity) use (&$capturedBook) {
                $capturedBook = $entity;
            });

        $this->service->createFromData($this->validBookData());

        $this->assertInstanceOf(Book::class, $capturedBook);
        $this->assertSame('The Great Gatsby', $capturedBook->getTitle());
        $this->assertSame('9780743273565', $capturedBook->getIsbn());
        $this->assertSame('the-great-gatsby', $capturedBook->getSlug());
        $this->assertSame(12.99, $capturedBook->getPrice());
        $this->assertSame(10, $capturedBook->getStock());
        $this->assertSame($category, $capturedBook->getCategory());
        $this->assertInstanceOf(\DateTime::class, $capturedBook->getPublishedAt());
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testCreateFromDataSetsDescriptionAndPublishedAtToNullWhenEmpty(): void
    {
        $this->categoryRepository->method('find')->willReturn(new Category());
        $this->validator->method('validate')->willReturn(new ConstraintViolationList());

        $capturedBook = null;
        $this->entityManager
            ->method('persist')
            ->willReturnCallback(function ($entity) use (&$capturedBook) {
                $capturedBook = $entity;
            });

        $this->service->createFromData($this->validBookData([
            'description' => '',
            'publishedAt' => '',
        ]));

        $this->assertNull($capturedBook->getDescription());
        $this->assertNull($capturedBook->getPublishedAt());
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testCreateFromDataAssignsPublisherWhenProvided(): void
    {
        $this->categoryRepository->method('find')->willReturn(new Category());
        $publisher = new Publisher();
        $this->publisherRepository->expects($this->once())->method('find')->with(5)->willReturn($publisher);
        $this->validator->method('validate')->willReturn(new ConstraintViolationList());

        $capturedBook = null;
        $this->entityManager
            ->method('persist')
            ->willReturnCallback(function ($entity) use (&$capturedBook) {
                $capturedBook = $entity;
            });

        $this->service->createFromData($this->validBookData(['publisherId' => 5]));

        $this->assertSame($publisher, $capturedBook->getPublisher());
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testCreateFromDataLeavesPublisherNullWhenNotProvided(): void
    {
        $this->categoryRepository->method('find')->willReturn(new Category());
        $this->publisherRepository->expects($this->never())->method('find');
        $this->validator->method('validate')->willReturn(new ConstraintViolationList());

        $capturedBook = null;
        $this->entityManager
            ->method('persist')
            ->willReturnCallback(function ($entity) use (&$capturedBook) {
                $capturedBook = $entity;
            });

        $this->service->createFromData($this->validBookData(['publisherId' => null]));

        $this->assertNull($capturedBook->getPublisher());
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testCreateFromDataAttachesAuthorsByGivenIds(): void
    {
        $this->categoryRepository->method('find')->willReturn(new Category());
        $authorOne = new Author();
        $authorTwo = new Author();
        $this->authorRepository
            ->method('find')
            ->willReturnMap([
                [1, $authorOne],
                [2, $authorTwo],
            ]);
        $this->validator->method('validate')->willReturn(new ConstraintViolationList());

        $capturedBook = null;
        $this->entityManager
            ->method('persist')
            ->willReturnCallback(function ($entity) use (&$capturedBook) {
                $capturedBook = $entity;
            });

        $this->service->createFromData($this->validBookData(['authorIds' => [1, 2]]));

        $this->assertCount(2, $capturedBook->getAuthors());
        $this->assertTrue($capturedBook->getAuthors()->contains($authorOne));
        $this->assertTrue($capturedBook->getAuthors()->contains($authorTwo));
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testCreateFromDataSkipsUnknownAuthorIds(): void
    {
        $this->categoryRepository->method('find')->willReturn(new Category());
        $this->authorRepository->method('find')->willReturn(null); // no author found
        $this->validator->method('validate')->willReturn(new ConstraintViolationList());

        $capturedBook = null;
        $this->entityManager
            ->method('persist')
            ->willReturnCallback(function ($entity) use (&$capturedBook) {
                $capturedBook = $entity;
            });

        $this->service->createFromData($this->validBookData(['authorIds' => [999]]));

        $this->assertCount(0, $capturedBook->getAuthors());
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testUpdateFromDataReturnsErrorsWithoutFlushingWhenTitleMissing(): void
    {
        $book = new Book();
        $book->setTitle('Original Title');

        $this->entityManager->expects($this->never())->method('flush');

        $errors = $this->service->updateFromData($book, $this->validBookData(['title' => '', 'categoryId' => null]));

        $this->assertContains('Title is required.', $errors);
        $this->assertSame('Original Title', $book->getTitle()); // unchanged
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testUpdateFromDataAppliesFieldsAndFlushesOnValidData(): void
    {
        $book = new Book();
        $book->setTitle('Old Title');
        $book->setIsbn('0000000000');
        $book->setSlug('old-slug');
        $book->setPrice(5.0);
        $book->setStock(1);

        $category = new Category();
        $this->categoryRepository->method('find')->willReturn($category);
        $this->validator->method('validate')->willReturn(new ConstraintViolationList());

        $this->entityManager->expects($this->once())->method('flush');
        $this->entityManager->expects($this->never())->method('persist'); // update, not create

        $errors = $this->service->updateFromData($book, $this->validBookData(['title' => 'New Title']));

        $this->assertSame([], $errors);
        $this->assertSame('New Title', $book->getTitle());
        $this->assertSame($category, $book->getCategory());
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testUpdateFromDataReplacesExistingAuthorsWithNewOnes(): void
    {
        $book = new Book();
        $book->setTitle('Old Title');
        $book->setIsbn('0000000000');
        $book->setSlug('old-slug');
        $book->setPrice(5.0);
        $book->setStock(1);

        $oldAuthor = new Author();
        $book->addAuthor($oldAuthor);

        $newAuthor = new Author();
        $this->categoryRepository->method('find')->willReturn(new Category());
        $this->authorRepository->expects($this->once())->method('find')->with(42)->willReturn($newAuthor);
        $this->validator->method('validate')->willReturn(new ConstraintViolationList());

        $this->service->updateFromData($book, $this->validBookData(['authorIds' => [42]]));

        $this->assertFalse($book->getAuthors()->contains($oldAuthor));
        $this->assertTrue($book->getAuthors()->contains($newAuthor));
        $this->assertCount(1, $book->getAuthors());
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testDeleteRemovesAndFlushesBook(): void
    {
        $book = new Book();
        $book->setTitle('To Be Deleted');

        $this->entityManager
            ->expects($this->once())
            ->method('remove')
            ->with($book);

        $this->entityManager->expects($this->once())->method('flush');

        $this->service->delete($book);
    }

    private function validBookData(array $overrides = []): array
    {
        return array_merge([
            'title' => 'The Great Gatsby',
            'isbn' => '9780743273565',
            'slug' => 'the-great-gatsby',
            'description' => 'A novel by F. Scott Fitzgerald.',
            'price' => '12.99',
            'stock' => '10',
            'publishedAt' => '1925-04-10',
            'categoryId' => 1,
            'publisherId' => null,
            'authorIds' => [],
        ], $overrides);
    }
}
