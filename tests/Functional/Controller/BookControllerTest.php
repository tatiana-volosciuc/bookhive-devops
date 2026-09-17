<?php

namespace App\Tests\Functional\Controller;

use App\Entity\Author;
use App\Entity\Book;
use App\Entity\Category;
use App\Entity\Publisher;
use App\Repository\BookRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class BookControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $entityManager;
    private BookRepository $bookRepository;

    protected function setUp(): void
    {
        parent::setUp();

        // Create the client ONCE per test. createClient() boots the kernel
        // internally — calling it again later (or calling bootKernel() separately)
        // throws "kernel should only be booted once".
        $this->client = static::createClient();

        $container = static::getContainer();
        $this->entityManager = $container->get(EntityManagerInterface::class);
        $this->bookRepository = $container->get(BookRepository::class);

        // Wipe tables before each test, respecting FK order: join table first,
        // then Book (which references Category/Publisher), then the lookup tables.
        $connection = $this->entityManager->getConnection();
        $connection->executeStatement('DELETE FROM book_author');
        $connection->executeStatement('DELETE FROM book');
        $connection->executeStatement('DELETE FROM author');
        $connection->executeStatement('DELETE FROM category');
        $connection->executeStatement('DELETE FROM publisher');
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        unset($this->client, $this->entityManager, $this->bookRepository);
    }

    private function createCategory(string $name = 'Fiction', string $slug = 'fiction'): Category
    {
        $category = new Category();
        $category->setName($name);
        $category->setSlug($slug);

        $this->entityManager->persist($category);
        $this->entityManager->flush();

        return $category;
    }

    private function createPublisher(string $name = 'Penguin Books'): Publisher
    {
        $publisher = new Publisher();
        $publisher->setName($name);

        $this->entityManager->persist($publisher);
        $this->entityManager->flush();

        return $publisher;
    }

    private function createAuthor(string $name = 'Jane Austen'): Author
    {
        $author = new Author();
        $author->setName($name);

        $this->entityManager->persist($author);
        $this->entityManager->flush();

        return $author;
    }

    private function createBook(Category $category, array $overrides = []): Book
    {
        $book = new Book();
        $book->setTitle($overrides['title'] ?? 'The Great Gatsby');
        $book->setIsbn($overrides['isbn'] ?? '9780743273565');
        $book->setSlug($overrides['slug'] ?? 'the-great-gatsby');
        $book->setPrice($overrides['price'] ?? 12.99);
        $book->setStock($overrides['stock'] ?? 10);
        $book->setCategory($category);

        if (isset($overrides['publisher'])) {
            $book->setPublisher($overrides['publisher']);
        }
        if (isset($overrides['authors'])) {
            foreach ($overrides['authors'] as $author) {
                $book->addAuthor($author);
            }
        }

        $this->entityManager->persist($book);
        $this->entityManager->flush();

        return $book;
    }

    // ----- GET /books -----

    public function testListReturns200(): void
    {
        $this->client->request('GET', '/books');

        $this->assertResponseIsSuccessful();
    }

    public function testListDisplaysExistingBooks(): void
    {
        $category = $this->createCategory();
        $this->createBook($category, ['title' => 'The Great Gatsby']);

        $this->client->request('GET', '/books');

        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString('The Great Gatsby', $this->client->getResponse()->getContent());
    }

    public function testListRejectsPost(): void
    {
        $this->client->request('POST', '/books');

        $this->assertResponseStatusCodeSame(405);
    }

    // ----- GET /books/new -----

    public function testNewFormReturns200OnGet(): void
    {
        $this->client->request('GET', '/books/new');

        $this->assertResponseIsSuccessful();
    }

    // ----- POST /books/new -----

    public function testNewCreatesBookAndRedirects(): void
    {
        $category = $this->createCategory();

        $this->client->request('POST', '/books/new', [
            'title' => 'Brave New World',
            'isbn' => '9780060850524',
            'slug' => 'brave-new-world',
            'price' => '9.99',
            'stock' => '5',
            'categoryId' => (string) $category->getId(),
        ]);

        $this->assertResponseRedirects('/books');

        $book = $this->bookRepository->findOneBy(['title' => 'Brave New World']);
        $this->assertNotNull($book);
        $this->assertSame($category->getId(), $book->getCategory()->getId());
    }

    public function testNewCreatesBookWithPublisherAndAuthors(): void
    {
        $category = $this->createCategory();
        $publisher = $this->createPublisher();
        $author = $this->createAuthor();

        $this->client->request('POST', '/books/new', [
            'title' => 'Brave New World',
            'isbn' => '9780060850524',
            'slug' => 'brave-new-world',
            'price' => '9.99',
            'stock' => '5',
            'categoryId' => (string) $category->getId(),
            'publisherId' => (string) $publisher->getId(),
            'authorIds' => [(string) $author->getId()],
        ]);

        $this->assertResponseRedirects('/books');

        $book = $this->bookRepository->findOneBy(['title' => 'Brave New World']);
        $this->assertNotNull($book);
        $this->assertSame($publisher->getId(), $book->getPublisher()->getId());
        $this->assertCount(1, $book->getAuthors());
    }

    public function testNewSetsSuccessFlashOnCreate(): void
    {
        $category = $this->createCategory();

        $this->client->request('POST', '/books/new', [
            'title' => 'Brave New World',
            'isbn' => '9780060850524',
            'slug' => 'brave-new-world',
            'price' => '9.99',
            'stock' => '5',
            'categoryId' => (string) $category->getId(),
        ]);

        $this->client->followRedirect();

        // ASSUMPTION: template renders flash messages somewhere visible in the body.
        $this->assertStringContainsString('created successfully', $this->client->getResponse()->getContent());
    }

    public function testNewWithMissingTitleReturnsFormWithErrors(): void
    {
        $category = $this->createCategory();

        $this->client->request('POST', '/books/new', [
            'title' => '',
            'isbn' => '9780060850524',
            'slug' => 'missing-title',
            'price' => '9.99',
            'stock' => '5',
            'categoryId' => (string) $category->getId(),
        ]);

        $this->assertResponseIsSuccessful(); // re-renders the form, doesn't redirect
        $this->assertNull($this->bookRepository->findOneBy(['slug' => 'missing-title']));
    }

    public function testNewWithMissingCategoryReturnsFormWithErrors(): void
    {
        $this->client->request('POST', '/books/new', [
            'title' => 'Orphan Book',
            'isbn' => '9780060850524',
            'slug' => 'orphan-book',
            'price' => '9.99',
            'stock' => '5',
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertNull($this->bookRepository->findOneBy(['slug' => 'orphan-book']));
    }

    public function testNewWithInvalidCategoryIdReturnsFormWithErrors(): void
    {
        $this->client->request('POST', '/books/new', [
            'title' => 'Orphan Book',
            'isbn' => '9780060850524',
            'slug' => 'orphan-book',
            'price' => '9.99',
            'stock' => '5',
            'categoryId' => '999999',
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertNull($this->bookRepository->findOneBy(['slug' => 'orphan-book']));
    }

    // ----- GET /books/{id}/edit -----

    public function testEditFormReturns200ForExistingBook(): void
    {
        $category = $this->createCategory();
        $book = $this->createBook($category);

        $this->client->request('GET', '/books/' . $book->getId() . '/edit');

        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString($book->getTitle(), $this->client->getResponse()->getContent());
    }

    public function testEditRedirectsWhenBookNotFound(): void
    {
        $this->client->request('GET', '/books/999999/edit');

        $this->assertResponseRedirects('/books');
    }

    public function testEditNotFoundSetsErrorFlash(): void
    {
        $this->client->request('GET', '/books/999999/edit');
        $this->client->followRedirect();

        $this->assertStringContainsString('Book not found', $this->client->getResponse()->getContent());
    }

    // ----- POST /books/{id}/edit -----

    public function testEditUpdatesBookAndRedirects(): void
    {
        $category = $this->createCategory();
        $book = $this->createBook($category, ['title' => 'Old Title', 'slug' => 'old-title']);

        $this->client->request('POST', '/books/' . $book->getId() . '/edit', [
            'title' => 'Updated Title',
            'isbn' => $book->getIsbn(),
            'slug' => 'updated-title',
            'price' => '15.00',
            'stock' => '3',
            'categoryId' => (string) $category->getId(),
        ]);

        $this->assertResponseRedirects('/books');

        $this->entityManager->refresh($book);
        $this->assertSame('Updated Title', $book->getTitle());
        $this->assertSame(15.0, $book->getPrice());
    }

    public function testEditReplacesAuthors(): void
    {
        $category = $this->createCategory();
        $originalAuthor = $this->createAuthor('Original Author');
        $book = $this->createBook($category, ['authors' => [$originalAuthor]]);

        $newAuthor = $this->createAuthor('New Author');

        $this->client->request('POST', '/books/' . $book->getId() . '/edit', [
            'title' => $book->getTitle(),
            'isbn' => $book->getIsbn(),
            'slug' => $book->getSlug(),
            'price' => (string) $book->getPrice(),
            'stock' => (string) $book->getStock(),
            'categoryId' => (string) $category->getId(),
            'authorIds' => [(string) $newAuthor->getId()],
        ]);

        $this->assertResponseRedirects('/books');

        $this->entityManager->refresh($book);
        $authorIds = array_map(fn ($a) => $a->getId(), $book->getAuthors()->toArray());
        $this->assertNotContains($originalAuthor->getId(), $authorIds);
        $this->assertContains($newAuthor->getId(), $authorIds);
    }

    public function testEditWithMissingTitleReturnsFormWithErrors(): void
    {
        $category = $this->createCategory();
        $book = $this->createBook($category, ['title' => 'Old Title']);

        $this->client->request('POST', '/books/' . $book->getId() . '/edit', [
            'title' => '',
            'isbn' => $book->getIsbn(),
            'slug' => $book->getSlug(),
            'price' => (string) $book->getPrice(),
            'stock' => (string) $book->getStock(),
            'categoryId' => (string) $category->getId(),
        ]);

        $this->assertResponseIsSuccessful();

        $this->entityManager->refresh($book);
        $this->assertSame('Old Title', $book->getTitle()); // unchanged
    }

    // ----- POST /books/{id}/delete -----

    public function testDeleteRemovesBookAndRedirects(): void
    {
        $category = $this->createCategory();
        $book = $this->createBook($category);
        $id = $book->getId();

        $this->client->request('POST', '/books/' . $id . '/delete');

        $this->assertResponseRedirects('/books');
        $this->assertNull($this->bookRepository->find($id));
    }

    public function testDeleteSetsSuccessFlash(): void
    {
        $category = $this->createCategory();
        $book = $this->createBook($category, ['title' => 'To Be Deleted']);

        $this->client->request('POST', '/books/' . $book->getId() . '/delete');
        $this->client->followRedirect();

        $this->assertStringContainsString('deleted', $this->client->getResponse()->getContent());
    }

    public function testDeleteRedirectsWhenBookNotFound(): void
    {
        $this->client->request('POST', '/books/999999/delete');

        $this->assertResponseRedirects('/books');
    }

    public function testDeleteRejectsGet(): void
    {
        $category = $this->createCategory();
        $book = $this->createBook($category);

        $this->client->request('GET', '/books/' . $book->getId() . '/delete');

        $this->assertResponseStatusCodeSame(405);
    }
}
