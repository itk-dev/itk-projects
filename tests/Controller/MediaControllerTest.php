<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Project;
use App\Entity\ProjectAttachment;
use App\Entity\ProjectImage;
use App\Tests\FunctionalTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class MediaControllerTest extends FunctionalTestCase
{
    public function testImageIsServedToAuthenticatedUsers(): void
    {
        $this->loginAsAdmin();
        $project = $this->anyProject();

        $image = (new ProjectImage())->setAlt('Test image');
        $image->setImageFile($this->upload('sample.png', 'image/png', $this->pngBytes()));
        $project->addImage($image);
        $em = $this->entityManager();
        $em->flush();

        $this->client->request('GET', sprintf('/media/image/%s', (string) $image->getId()));
        $this->assertResponseIsSuccessful();

        $em->remove($image);
        $em->flush();
    }

    public function testAttachmentIsServedToAuthenticatedUsers(): void
    {
        $this->loginAsAdmin();
        $project = $this->anyProject();

        $attachment = new ProjectAttachment();
        $attachment->setFile($this->upload('document.pdf', 'application/pdf', "%PDF-1.4\n%%EOF\n"));
        $project->addAttachment($attachment);
        $em = $this->entityManager();
        $em->flush();

        $this->client->request('GET', sprintf('/media/attachment/%s', (string) $attachment->getId()));
        $this->assertResponseIsSuccessful();

        $em->remove($attachment);
        $em->flush();
    }

    private function anyProject(): Project
    {
        $project = $this->projects()->findOneBy([]);
        self::assertInstanceOf(Project::class, $project, 'Fixtures should provide at least one project.');

        return $project;
    }

    private function upload(string $name, string $mimeType, string $contents): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'itk');
        self::assertIsString($path);
        file_put_contents($path, $contents);

        return new UploadedFile($path, $name, $mimeType, null, true);
    }

    private function pngBytes(): string
    {
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==', true);
        self::assertIsString($png);

        return $png;
    }
}
