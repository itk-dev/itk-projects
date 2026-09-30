<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\ProjectCharacter;
use PHPUnit\Framework\TestCase;

final class ProjectCharacterTest extends TestCase
{
    public function testDefaults(): void
    {
        $character = new ProjectCharacter();

        self::assertNull($character->getName());
        self::assertSame('', (string) $character);
    }

    public function testAccessors(): void
    {
        $character = (new ProjectCharacter())->setName('Drift');

        self::assertSame('Drift', $character->getName());
        self::assertSame('Drift', (string) $character);
    }
}
