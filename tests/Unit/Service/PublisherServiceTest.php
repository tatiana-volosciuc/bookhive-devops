<?php

namespace App\Tests\Unit\Service;

use App\Entity\Publisher;
use App\Service\PublisherService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class PublisherServiceTest extends TestCase
{
    private EntityManagerInterface&MockObject $entityManager;
    private ValidatorInterface&Stub $validator;
    private PublisherService $service;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->validator = $this->createStub(ValidatorInterface::class);

        $this->service = new PublisherService($this->entityManager, $this->validator);
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testValidateInputReturnsErrorWhenNameMissing(): void
    {
        $errors = $this->service->validateInput(['name' => '', 'website' => 'https://example.com']);

        $this->assertSame(['Name is required.'], $errors);
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testValidateInputReturnsNoErrorsWhenNamePresent(): void
    {
        $errors = $this->service->validateInput(['name' => 'Penguin Books', 'website' => 'https://penguin.co.uk']);

        $this->assertSame([], $errors);
    }

    public function testCreateFromDataReturnsErrorsWithoutPersistingWhenNameMissing(): void
    {
        $this->entityManager->expects($this->never())->method('persist');
        $this->entityManager->expects($this->never())->method('flush');

        $errors = $this->service->createFromData(['name' => '', 'website' => 'https://example.com']);

        $this->assertSame(['Name is required.'], $errors);
    }

    public function testCreateFromDataReturnsEntityValidationErrorsWithoutPersisting(): void
    {
        $violation = new ConstraintViolation(
            'Website is not a valid URL.',
            null,
            [],
            '',
            'website',
            'not-a-url'
        );

        $this->validator
            ->method('validate')
            ->willReturn(new ConstraintViolationList([$violation]));

        $this->entityManager->expects($this->never())->method('persist');
        $this->entityManager->expects($this->never())->method('flush');

        $errors = $this->service->createFromData([
            'name' => 'Penguin Books',
            'website' => 'not-a-url',
            'description' => 'Some description.',
        ]);

        $this->assertSame(['Website is not a valid URL.'], $errors);
    }

    public function testCreateFromDataPersistsAndFlushesOnValidData(): void
    {
        $this->validator
            ->method('validate')
            ->willReturn(new ConstraintViolationList());

        $this->entityManager
            ->expects($this->once())
            ->method('persist')
            ->with($this->isInstanceOf(Publisher::class));

        $this->entityManager->expects($this->once())->method('flush');

        $errors = $this->service->createFromData([
            'name' => 'Penguin Books',
            'website' => 'https://penguin.co.uk',
            'description' => 'A major publishing house.',
        ]);

        $this->assertSame([], $errors);
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testCreateFromDataSetsFieldsOnNewPublisher(): void
    {
        $this->validator
            ->method('validate')
            ->willReturn(new ConstraintViolationList());

        $capturedPublisher = null;
        $this->entityManager
            ->method('persist')
            ->willReturnCallback(function ($entity) use (&$capturedPublisher) {
                $capturedPublisher = $entity;
            });

        $this->service->createFromData([
            'name' => 'HarperCollins',
            'website' => 'https://harpercollins.com',
            'description' => 'Global publisher.',
        ]);

        $this->assertInstanceOf(Publisher::class, $capturedPublisher);
        $this->assertSame('HarperCollins', $capturedPublisher->getName());
        $this->assertSame('https://harpercollins.com', $capturedPublisher->getWebsite());
        $this->assertSame('Global publisher.', $capturedPublisher->getDescription());
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testCreateFromDataSetsWebsiteAndDescriptionToNullWhenEmpty(): void
    {
        $this->validator
            ->method('validate')
            ->willReturn(new ConstraintViolationList());

        $capturedPublisher = null;
        $this->entityManager
            ->method('persist')
            ->willReturnCallback(function ($entity) use (&$capturedPublisher) {
                $capturedPublisher = $entity;
            });

        $this->service->createFromData([
            'name' => 'Small Press',
            'website' => '',
            'description' => '',
        ]);

        $this->assertNull($capturedPublisher->getWebsite());
        $this->assertNull($capturedPublisher->getDescription());
    }

    public function testUpdateFromDataReturnsErrorsWithoutFlushingWhenNameMissing(): void
    {
        $publisher = new Publisher();
        $publisher->setName('Original Name');

        $this->entityManager->expects($this->never())->method('flush');

        $errors = $this->service->updateFromData($publisher, ['name' => '', 'website' => 'x']);

        $this->assertSame(['Name is required.'], $errors);
        $this->assertSame('Original Name', $publisher->getName()); // unchanged
    }

    public function testUpdateFromDataReturnsEntityValidationErrorsWithoutFlushing(): void
    {
        $publisher = new Publisher();
        $publisher->setName('Original Name');

        $violation = new ConstraintViolation(
            'Website is not a valid URL.',
            null,
            [],
            '',
            'website',
            'not-a-url'
        );

        $this->validator
            ->method('validate')
            ->willReturn(new ConstraintViolationList([$violation]));

        $this->entityManager->expects($this->never())->method('flush');

        $errors = $this->service->updateFromData($publisher, [
            'name' => 'Updated Name',
            'website' => 'not-a-url',
            'description' => 'Some description.',
        ]);

        $this->assertSame(['Website is not a valid URL.'], $errors);
    }

    public function testUpdateFromDataAppliesFieldsAndFlushesOnValidData(): void
    {
        $publisher = new Publisher();
        $publisher->setName('Original Name');
        $publisher->setWebsite('https://original.com');
        $publisher->setDescription('Original description.');

        $this->validator
            ->method('validate')
            ->willReturn(new ConstraintViolationList());

        $this->entityManager->expects($this->once())->method('flush');
        $this->entityManager->expects($this->never())->method('persist'); // update, not create

        $errors = $this->service->updateFromData($publisher, [
            'name' => 'Updated Name',
            'website' => 'https://updated.com',
            'description' => 'Updated description.',
        ]);

        $this->assertSame([], $errors);
        $this->assertSame('Updated Name', $publisher->getName());
        $this->assertSame('https://updated.com', $publisher->getWebsite());
    }

    public function testDeleteRemovesAndFlushesPublisher(): void
    {
        $publisher = new Publisher();
        $publisher->setName('To Be Deleted');

        $this->entityManager
            ->expects($this->once())
            ->method('remove')
            ->with($publisher);

        $this->entityManager->expects($this->once())->method('flush');

        $this->service->delete($publisher);
    }
}
