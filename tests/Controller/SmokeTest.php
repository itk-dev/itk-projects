<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\InitiativeImage;
use App\Repository\InitiativeRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class SmokeTest extends WebTestCase
{
    public function testDashboardRequiresLogin(): void
    {
        $client = static::createClient();
        $client->request('GET', '/');
        $this->assertResponseRedirects();
    }

    #[DataProvider('authenticatedPages')]
    public function testAuthenticatedPagesRender(string $url): void
    {
        $client = static::createClient();
        $admin = static::getContainer()->get(UserRepository::class)->findOneBy(['email' => 'admin@example.com']);
        self::assertNotNull($admin, 'Load fixtures into the test database first.');

        $client->loginUser($admin);
        $client->request('GET', $url);

        $this->assertResponseIsSuccessful(sprintf('GET %s should succeed', $url));
    }

    public function testInitiativeShowAndEditRender(): void
    {
        $client = static::createClient();
        $container = static::getContainer();

        $admin = $container->get(UserRepository::class)->findOneBy(['email' => 'admin@example.com']);
        self::assertNotNull($admin);
        $client->loginUser($admin);

        $initiative = $container->get(InitiativeRepository::class)->findOneBy([]);
        self::assertNotNull($initiative, 'Fixtures should create at least one initiative.');

        $client->request('GET', sprintf('/initiatives/%s', $initiative->getId()));
        $this->assertResponseIsSuccessful();

        $client->request('GET', sprintf('/initiatives/%s/edit', $initiative->getId()));
        $this->assertResponseIsSuccessful();
    }

    public function testImageUploadIsStored(): void
    {
        static::createClient();
        $container = static::getContainer();
        $entityManager = $container->get(EntityManagerInterface::class);

        $initiative = $container->get(InitiativeRepository::class)->findOneBy([]);
        self::assertNotNull($initiative);

        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==', true);
        self::assertIsString($png);
        $path = tempnam(sys_get_temp_dir(), 'itk').'.png';
        file_put_contents($path, $png);
        $upload = new UploadedFile($path, 'sample.png', 'image/png', null, true);

        $image = (new InitiativeImage())->setAlt('Sample');
        $image->setImageFile($upload);
        $initiative->addImage($image);
        $entityManager->flush();

        self::assertNotNull($image->getImageName(), 'Vich should persist the stored file name.');
        self::assertSame('sample.png', $image->getOriginalName());

        // Keep the suite idempotent (and let Vich delete the stored file).
        $entityManager->remove($image);
        $entityManager->flush();
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function authenticatedPages(): iterable
    {
        yield 'dashboard' => ['/'];
        yield 'initiatives' => ['/initiatives'];
        yield 'initiatives filtered' => ['/initiatives?status=granted&endorsement=1&sort=title&direction=ASC'];
        yield 'initiatives freetext search' => ['/initiatives?q=teknik'];
        yield 'initiative new' => ['/initiatives/new'];
        yield 'csv export' => ['/initiatives/export'];
        yield 'admin users' => ['/admin/users'];
        yield 'admin user new' => ['/admin/users/new'];
        yield 'admin contacts' => ['/admin/contacts'];
        yield 'admin contact new' => ['/admin/contacts/new'];
        yield 'admin partners' => ['/admin/partners'];
        yield 'admin partner new' => ['/admin/partners/new'];
        yield 'admin departments' => ['/admin/departments'];
        yield 'admin department new' => ['/admin/departments/new'];
    }
}
