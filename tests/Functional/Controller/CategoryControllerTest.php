<?php

namespace App\Tests\Functional\Controller;

use App\Entity\Category;
use App\Repository\CategoryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class CategoryControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $entityManager;
    private CategoryRepository $categoryRepository;

    protected function setUp(): void
    {
        parent::setUp();

        // Create the client ONCE per test. createClient() boots the kernel
        // internally — calling it again later (or calling bootKernel() separately)
        // throws "kernel should only be booted once".
        $this->client = static::createClient();

        $container = static::getContainer();
        $this->entityManager = $container->get(EntityManagerInterface::class);
        $this->categoryRepository = $container->get(CategoryRepository::class);

        // Wipe the table before each test so tests don't leak state into each other.
        // If you set up DAMADoctrineTestBundle for transaction rollback, you can
        // remove this line.
        $this->entityManager->createQuery('DELETE FROM App\Entity\Category')->execute();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        unset($this->client, $this->entityManager, $this->categoryRepository);
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

    // ----- GET /categories -----

    public function testListReturns200(): void
    {
        $this->client->request('GET', '/categories');

        $this->assertResponseIsSuccessful();
    }

    public function testListDisplaysExistingCategories(): void
    {
        $this->createCategory('Fiction', 'fiction');

        $this->client->request('GET', '/categories');

        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString('Fiction', $this->client->getResponse()->getContent());
    }

    public function testListRejectsPost(): void
    {
        $this->client->request('POST', '/categories');

        $this->assertResponseStatusCodeSame(405);
    }

    // ----- GET /categories/new -----

    public function testNewFormReturns200OnGet(): void
    {
        $this->client->request('GET', '/categories/new');

        $this->assertResponseIsSuccessful();
    }

    // ----- POST /categories/new -----

    public function testNewCreatesCategoryAndRedirects(): void
    {
        $this->client->request('POST', '/categories/new', [
            'name' => 'Non-Fiction',
            'slug' => 'non-fiction',
        ]);

        $this->assertResponseRedirects('/categories');

        $category = $this->categoryRepository->findOneBy(['name' => 'Non-Fiction']);
        $this->assertNotNull($category);
        $this->assertSame('non-fiction', $category->getSlug());
    }

    public function testNewSetsSuccessFlashOnCreate(): void
    {
        $this->client->request('POST', '/categories/new', [
            'name' => 'Non-Fiction',
            'slug' => 'non-fiction',
        ]);

        $this->client->followRedirect();

        // ASSUMPTION: template renders flash messages somewhere visible in the body.
        $this->assertStringContainsString('created successfully', $this->client->getResponse()->getContent());
    }

    public function testNewWithMissingNameReturnsFormWithErrors(): void
    {
        $this->client->request('POST', '/categories/new', [
            'name' => '',
            'slug' => 'missing-name',
        ]);

        $this->assertResponseIsSuccessful(); // re-renders the form, doesn't redirect
        $this->assertNull($this->categoryRepository->findOneBy(['slug' => 'missing-name']));
    }

    public function testNewWithMissingSlugReturnsFormWithErrors(): void
    {
        $this->client->request('POST', '/categories/new', [
            'name' => 'Missing Slug',
            'slug' => '',
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertNull($this->categoryRepository->findOneBy(['name' => 'Missing Slug']));
    }

    // ----- GET /categories/{id}/edit -----

    public function testEditFormReturns200ForExistingCategory(): void
    {
        $category = $this->createCategory();

        $this->client->request('GET', '/categories/' . $category->getId() . '/edit');

        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString($category->getName(), $this->client->getResponse()->getContent());
    }

    public function testEditRedirectsWhenCategoryNotFound(): void
    {
        $this->client->request('GET', '/categories/999999/edit');

        $this->assertResponseRedirects('/categories');
    }

    public function testEditNotFoundSetsErrorFlash(): void
    {
        $this->client->request('GET', '/categories/999999/edit');
        $this->client->followRedirect();

        $this->assertStringContainsString('Category not found', $this->client->getResponse()->getContent());
    }

    // ----- POST /categories/{id}/edit -----

    public function testEditUpdatesCategoryAndRedirects(): void
    {
        $category = $this->createCategory('Old Name', 'old-slug');

        $this->client->request('POST', '/categories/' . $category->getId() . '/edit', [
            'name' => 'Updated Name',
            'slug' => 'updated-slug',
        ]);

        $this->assertResponseRedirects('/categories');

        $this->entityManager->refresh($category);
        $this->assertSame('Updated Name', $category->getName());
        $this->assertSame('updated-slug', $category->getSlug());
    }

    public function testEditWithMissingNameReturnsFormWithErrors(): void
    {
        $category = $this->createCategory('Old Name', 'old-slug');

        $this->client->request('POST', '/categories/' . $category->getId() . '/edit', [
            'name' => '',
            'slug' => 'still-old-slug',
        ]);

        $this->assertResponseIsSuccessful();

        $this->entityManager->refresh($category);
        $this->assertSame('Old Name', $category->getName()); // unchanged
    }

    // ----- POST /categories/{id}/delete -----

    public function testDeleteRemovesCategoryAndRedirects(): void
    {
        $category = $this->createCategory();
        $id = $category->getId();

        $this->client->request('POST', '/categories/' . $id . '/delete');

        $this->assertResponseRedirects('/categories');
        $this->assertNull($this->categoryRepository->find($id));
    }

    public function testDeleteSetsSuccessFlash(): void
    {
        $category = $this->createCategory('Mystery', 'mystery');

        $this->client->request('POST', '/categories/' . $category->getId() . '/delete');
        $this->client->followRedirect();

        $this->assertStringContainsString('deleted', $this->client->getResponse()->getContent());
    }

    public function testDeleteRedirectsWhenCategoryNotFound(): void
    {
        $this->client->request('POST', '/categories/999999/delete');

        $this->assertResponseRedirects('/categories');
    }

    public function testDeleteRejectsGet(): void
    {
        $category = $this->createCategory();

        $this->client->request('GET', '/categories/' . $category->getId() . '/delete');

        $this->assertResponseStatusCodeSame(405);
    }
}
