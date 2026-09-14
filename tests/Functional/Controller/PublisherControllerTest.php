<?php

namespace App\Tests\Functional\Controller;

use App\Entity\Publisher;
use App\Repository\PublisherRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class PublisherControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $entityManager;
    private PublisherRepository $publisherRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = static::createClient();

        $container = static::getContainer();
        $this->entityManager = $container->get(EntityManagerInterface::class);
        $this->publisherRepository = $container->get(PublisherRepository::class);

        $this->entityManager->createQuery('DELETE FROM App\Entity\Publisher')->execute();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        unset($this->client, $this->entityManager, $this->publisherRepository);
    }

    private function createPublisher(
        string $name = 'Penguin Books',
        ?string $website = 'https://penguin.co.uk',
        ?string $description = 'A major publishing house.'
    ): Publisher {
        $publisher = new Publisher();
        $publisher->setName($name);
        $publisher->setWebsite($website);
        $publisher->setDescription($description);

        $this->entityManager->persist($publisher);
        $this->entityManager->flush();

        return $publisher;
    }

    public function testListReturns200(): void
    {
        $this->client->request('GET', '/publishers');

        $this->assertResponseIsSuccessful();
    }

    public function testListDisplaysExistingPublishers(): void
    {
        $this->createPublisher();

        $this->client->request('GET', '/publishers');

        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString('Penguin Books', $this->client->getResponse()->getContent());
    }

    public function testListRejectsPost(): void
    {
        $this->client->request('POST', '/publishers');

        $this->assertResponseStatusCodeSame(405);
    }

    public function testNewFormReturns200OnGet(): void
    {
        $this->client->request('GET', '/publishers/new');

        $this->assertResponseIsSuccessful();
    }

    public function testNewCreatesPublisherAndRedirects(): void
    {
        $this->client->request('POST', '/publishers/new', [
            'name' => 'HarperCollins',
            'website' => 'https://harpercollins.com',
            'description' => 'Global publisher.',
        ]);

        $this->assertResponseRedirects('/publishers');

        $publisher = $this->publisherRepository->findOneBy(['name' => 'HarperCollins']);
        $this->assertNotNull($publisher);
        $this->assertSame('https://harpercollins.com', $publisher->getWebsite());
    }

    public function testNewCreatesPublisherWithoutOptionalFields(): void
    {
        $this->client->request('POST', '/publishers/new', [
            'name' => 'Small Press',
            'website' => '',
            'description' => '',
        ]);

        $this->assertResponseRedirects('/publishers');

        $publisher = $this->publisherRepository->findOneBy(['name' => 'Small Press']);
        $this->assertNotNull($publisher);
        $this->assertNull($publisher->getWebsite());
        $this->assertNull($publisher->getDescription());
    }

    public function testNewSetsSuccessFlashOnCreate(): void
    {
        $this->client->request('POST', '/publishers/new', [
            'name' => 'HarperCollins',
            'website' => 'https://harpercollins.com',
            'description' => 'Global publisher.',
        ]);

        $this->client->followRedirect();

        $this->assertStringContainsString('created successfully', $this->client->getResponse()->getContent());
    }

    public function testNewWithMissingNameReturnsFormWithErrors(): void
    {
        $this->client->request('POST', '/publishers/new', [
            'name' => '',
            'website' => 'https://example.com',
            'description' => 'Missing a name.',
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertNull($this->publisherRepository->findOneBy(['website' => 'https://example.com']));
    }

    public function testEditFormReturns200ForExistingPublisher(): void
    {
        $publisher = $this->createPublisher();

        $this->client->request('GET', '/publishers/' . $publisher->getId() . '/edit');

        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString($publisher->getName(), $this->client->getResponse()->getContent());
    }

    public function testEditRedirectsWhenPublisherNotFound(): void
    {
        $this->client->request('GET', '/publishers/999999/edit');

        $this->assertResponseRedirects('/publishers');
    }

    public function testEditNotFoundSetsErrorFlash(): void
    {
        $this->client->request('GET', '/publishers/999999/edit');
        $this->client->followRedirect();

        $this->assertStringContainsString('Publisher not found', $this->client->getResponse()->getContent());
    }

    public function testEditUpdatesPublisherAndRedirects(): void
    {
        $publisher = $this->createPublisher('Old Name', 'https://old.com', 'Old description.');

        $this->client->request('POST', '/publishers/' . $publisher->getId() . '/edit', [
            'name' => 'Updated Name',
            'website' => 'https://updated.com',
            'description' => 'Updated description.',
        ]);

        $this->assertResponseRedirects('/publishers');

        $this->entityManager->refresh($publisher);
        $this->assertSame('Updated Name', $publisher->getName());
        $this->assertSame('https://updated.com', $publisher->getWebsite());
    }

    public function testEditWithMissingNameReturnsFormWithErrors(): void
    {
        $publisher = $this->createPublisher('Old Name');

        $this->client->request('POST', '/publishers/' . $publisher->getId() . '/edit', [
            'name' => '',
            'website' => 'https://old.com',
            'description' => 'Still has old data.',
        ]);

        $this->assertResponseIsSuccessful();

        $this->entityManager->refresh($publisher);
        $this->assertSame('Old Name', $publisher->getName());
    }

    public function testDeleteRemovesPublisherAndRedirects(): void
    {
        $publisher = $this->createPublisher();
        $id = $publisher->getId();

        $this->client->request('POST', '/publishers/' . $id . '/delete');

        $this->assertResponseRedirects('/publishers');
        $this->assertNull($this->publisherRepository->find($id));
    }

    public function testDeleteSetsSuccessFlash(): void
    {
        $publisher = $this->createPublisher('Vintage Books');

        $this->client->request('POST', '/publishers/' . $publisher->getId() . '/delete');
        $this->client->followRedirect();

        $this->assertStringContainsString('deleted', $this->client->getResponse()->getContent());
    }

    public function testDeleteRedirectsWhenPublisherNotFound(): void
    {
        $this->client->request('POST', '/publishers/999999/delete');

        $this->assertResponseRedirects('/publishers');
    }

    public function testDeleteRejectsGet(): void
    {
        $publisher = $this->createPublisher();

        $this->client->request('GET', '/publishers/' . $publisher->getId() . '/delete');

        $this->assertResponseStatusCodeSame(405);
    }
}
