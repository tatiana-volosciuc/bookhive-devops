<?php

namespace App\Tests\Functional\Controller;

use App\Entity\Author;
use App\Repository\AuthorRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class AuthorControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $entityManager;
    private AuthorRepository $authorRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = static::createClient();

        $container = static::getContainer();
        $this->entityManager = $container->get(EntityManagerInterface::class);
        $this->authorRepository = $container->get(AuthorRepository::class);

        $this->entityManager->createQuery('DELETE FROM App\Entity\Author')->execute();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        unset($this->client, $this->entityManager, $this->authorRepository);
    }

    private function createAuthor(string $name = 'Jane Austen', string $bio = 'English novelist.'): Author
    {
        $author = new Author();
        $author->setName($name);
        $author->setBio($bio);

        $this->entityManager->persist($author);
        $this->entityManager->flush();

        return $author;
    }

    // ----- GET /authors -----

    public function testListReturns200(): void
    {
        $this->client->request('GET', '/authors');

        $this->assertResponseIsSuccessful();
    }

    public function testListDisplaysExistingAuthors(): void
    {
        $this->createAuthor('Jane Austen');

        $this->client->request('GET', '/authors');

        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString('Jane Austen', $this->client->getResponse()->getContent());
    }

    public function testListRejectsPost(): void
    {
        $this->client->request('POST', '/authors');

        $this->assertResponseStatusCodeSame(405);
    }

    // ----- GET /authors/new -----

    public function testNewFormReturns200OnGet(): void
    {
        $this->client->request('GET', '/authors/new');

        $this->assertResponseIsSuccessful();
    }

    // ----- POST /authors/new -----

    public function testNewCreatesAuthorAndRedirects(): void
    {
        $this->client->request('POST', '/authors/new', [
            'name' => 'George Orwell',
            'bio' => 'English novelist and essayist.',
        ]);

        $this->assertResponseRedirects('/authors');

        $author = $this->authorRepository->findOneBy(['name' => 'George Orwell']);
        $this->assertNotNull($author);
    }

    public function testNewSetsSuccessFlashOnCreate(): void
    {
        $this->client->request('POST', '/authors/new', [
            'name' => 'George Orwell',
            'bio' => 'English novelist and essayist.',
        ]);

        $this->client->followRedirect();

        // ASSUMPTION: template renders flash messages somewhere visible in the body.
        $this->assertStringContainsString('created successfully', $this->client->getResponse()->getContent());
    }

    public function testNewWithInvalidDataReturnsFormWithErrors(): void
    {
        $this->client->request('POST', '/authors/new', [
            'name' => '',
            'bio' => 'Missing a name.',
        ]);

        $this->assertResponseIsSuccessful(); // re-renders the form, doesn't redirect
        $this->assertNull($this->authorRepository->findOneBy(['bio' => 'Missing a name.']));
    }

    // ----- GET /authors/{id}/edit -----

    public function testEditFormReturns200ForExistingAuthor(): void
    {
        $author = $this->createAuthor();

        $this->client->request('GET', '/authors/' . $author->getId() . '/edit');

        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString($author->getName(), $this->client->getResponse()->getContent());
    }

    public function testEditRedirectsWhenAuthorNotFound(): void
    {
        $this->client->request('GET', '/authors/999999/edit');

        $this->assertResponseRedirects('/authors');
    }

    public function testEditNotFoundSetsErrorFlash(): void
    {
        $this->client->request('GET', '/authors/999999/edit');
        $this->client->followRedirect();

        $this->assertStringContainsString('Author not found', $this->client->getResponse()->getContent());
    }

    // ----- POST /authors/{id}/edit -----

    public function testEditUpdatesAuthorAndRedirects(): void
    {
        $author = $this->createAuthor('Old Name');

        $this->client->request('POST', '/authors/' . $author->getId() . '/edit', [
            'name' => 'Updated Name',
            'bio' => 'Updated bio.',
        ]);

        $this->assertResponseRedirects('/authors');

        $this->entityManager->refresh($author);
        $this->assertSame('Updated Name', $author->getName());
    }

    public function testEditWithInvalidDataReturnsFormWithErrors(): void
    {
        $author = $this->createAuthor('Old Name');

        $this->client->request('POST', '/authors/' . $author->getId() . '/edit', [
            'name' => '',
            'bio' => 'Still has old data.',
        ]);

        $this->assertResponseIsSuccessful();

        $this->entityManager->refresh($author);
        $this->assertSame('Old Name', $author->getName()); // unchanged
    }

    // ----- POST /authors/{id}/delete -----

    public function testDeleteRemovesAuthorAndRedirects(): void
    {
        $author = $this->createAuthor();
        $id = $author->getId();

        $this->client->request('POST', '/authors/' . $id . '/delete');

        $this->assertResponseRedirects('/authors');
        $this->assertNull($this->authorRepository->find($id));
    }

    public function testDeleteSetsSuccessFlash(): void
    {
        $author = $this->createAuthor('Toni Morrison');

        $this->client->request('POST', '/authors/' . $author->getId() . '/delete');
        $this->client->followRedirect();

        $this->assertStringContainsString('deleted', $this->client->getResponse()->getContent());
    }

    public function testDeleteRedirectsWhenAuthorNotFound(): void
    {
        $this->client->request('POST', '/authors/999999/delete');

        $this->assertResponseRedirects('/authors');
    }

    public function testDeleteRejectsGet(): void
    {
        $author = $this->createAuthor();

        $this->client->request('GET', '/authors/' . $author->getId() . '/delete');

        $this->assertResponseStatusCodeSame(405);
    }
}
