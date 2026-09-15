<?php

namespace App\Tests\Unit\Service;

use App\Entity\Category;
use App\Service\CategoryService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class CategoryServiceTest extends TestCase
{
    private EntityManagerInterface&MockObject $entityManager;
    private ValidatorInterface&Stub $validator;
    private CategoryService $service;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->validator = $this->createStub(ValidatorInterface::class);

        $this->service = new CategoryService($this->entityManager, $this->validator);
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testValidateInputReturnsErrorWhenNameMissing(): void
    {
        $errors = $this->service->validateInput(['name' => '', 'slug' => 'fiction']);

        $this->assertSame(['Name is required.'], $errors);
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testValidateInputReturnsErrorWhenSlugMissing(): void
    {
        $errors = $this->service->validateInput(['name' => 'Fiction', 'slug' => '']);

        $this->assertSame(['Slug is required.'], $errors);
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testValidateInputReturnsBothErrorsWhenBothMissing(): void
    {
        $errors = $this->service->validateInput(['name' => '', 'slug' => '']);

        $this->assertCount(2, $errors);
        $this->assertContains('Name is required.', $errors);
        $this->assertContains('Slug is required.', $errors);
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testValidateInputReturnsNoErrorsWhenBothPresent(): void
    {
        $errors = $this->service->validateInput(['name' => 'Fiction', 'slug' => 'fiction']);

        $this->assertSame([], $errors);
    }

    public function testCreateFromDataReturnsErrorsWithoutPersistingWhenNameMissing(): void
    {
        $this->entityManager->expects($this->never())->method('persist');
        $this->entityManager->expects($this->never())->method('flush');

        $errors = $this->service->createFromData(['name' => '', 'slug' => 'fiction']);

        $this->assertSame(['Name is required.'], $errors);
    }

    public function testCreateFromDataReturnsErrorsWithoutPersistingWhenSlugMissing(): void
    {
        $this->entityManager->expects($this->never())->method('persist');
        $this->entityManager->expects($this->never())->method('flush');

        $errors = $this->service->createFromData(['name' => 'Fiction', 'slug' => '']);

        $this->assertSame(['Slug is required.'], $errors);
    }

    public function testCreateFromDataReturnsEntityValidationErrorsWithoutPersisting(): void
    {
        $violation = new ConstraintViolation(
            'Slug is already in use.',
            null,
            [],
            '',
            'slug',
            'fiction'
        );

        $this->validator
            ->method('validate')
            ->willReturn(new ConstraintViolationList([$violation]));

        $this->entityManager->expects($this->never())->method('persist');
        $this->entityManager->expects($this->never())->method('flush');

        $errors = $this->service->createFromData([
            'name' => 'Fiction',
            'slug' => 'fiction',
        ]);

        $this->assertSame(['Slug is already in use.'], $errors);
    }

    public function testCreateFromDataPersistsAndFlushesOnValidData(): void
    {
        $this->validator
            ->method('validate')
            ->willReturn(new ConstraintViolationList());

        $this->entityManager
            ->expects($this->once())
            ->method('persist')
            ->with($this->isInstanceOf(Category::class));

        $this->entityManager->expects($this->once())->method('flush');

        $errors = $this->service->createFromData([
            'name' => 'Fiction',
            'slug' => 'fiction',
        ]);

        $this->assertSame([], $errors);
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testCreateFromDataSetsFieldsOnNewCategory(): void
    {
        $this->validator
            ->method('validate')
            ->willReturn(new ConstraintViolationList());

        $capturedCategory = null;
        $this->entityManager
            ->method('persist')
            ->willReturnCallback(function ($entity) use (&$capturedCategory) {
                $capturedCategory = $entity;
            });

        $this->service->createFromData([
            'name' => 'Science Fiction',
            'slug' => 'sci-fi',
        ]);

        $this->assertInstanceOf(Category::class, $capturedCategory);
        $this->assertSame('Science Fiction', $capturedCategory->getName());
        $this->assertSame('sci-fi', $capturedCategory->getSlug());
    }

    public function testUpdateFromDataReturnsErrorsWithoutFlushingWhenNameMissing(): void
    {
        $category = new Category();
        $category->setName('Original Name');
        $category->setSlug('original-slug');

        $this->entityManager->expects($this->never())->method('flush');

        $errors = $this->service->updateFromData($category, ['name' => '', 'slug' => 'x']);

        $this->assertSame(['Name is required.'], $errors);
        $this->assertSame('Original Name', $category->getName());
    }

    public function testUpdateFromDataReturnsEntityValidationErrorsWithoutFlushing(): void
    {
        $category = new Category();
        $category->setName('Original Name');
        $category->setSlug('original-slug');

        $violation = new ConstraintViolation(
            'Slug is already in use.',
            null,
            [],
            '',
            'slug',
            'taken-slug'
        );

        $this->validator
            ->method('validate')
            ->willReturn(new ConstraintViolationList([$violation]));

        $this->entityManager->expects($this->never())->method('flush');

        $errors = $this->service->updateFromData($category, [
            'name' => 'Updated Name',
            'slug' => 'taken-slug',
        ]);

        $this->assertSame(['Slug is already in use.'], $errors);
    }

    public function testUpdateFromDataAppliesFieldsAndFlushesOnValidData(): void
    {
        $category = new Category();
        $category->setName('Original Name');
        $category->setSlug('original-slug');

        $this->validator
            ->method('validate')
            ->willReturn(new ConstraintViolationList());

        $this->entityManager->expects($this->once())->method('flush');
        $this->entityManager->expects($this->never())->method('persist');

        $errors = $this->service->updateFromData($category, [
            'name' => 'Updated Name',
            'slug' => 'updated-slug',
        ]);

        $this->assertSame([], $errors);
        $this->assertSame('Updated Name', $category->getName());
        $this->assertSame('updated-slug', $category->getSlug());
    }

    public function testDeleteRemovesAndFlushesCategory(): void
    {
        $category = new Category();
        $category->setName('To Be Deleted');
        $category->setSlug('to-be-deleted');

        $this->entityManager
            ->expects($this->once())
            ->method('remove')
            ->with($category);

        $this->entityManager->expects($this->once())->method('flush');

        $this->service->delete($category);
    }
}
