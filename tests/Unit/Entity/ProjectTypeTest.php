<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\ProjectType;
use PHPUnit\Framework\TestCase;

final class ProjectTypeTest extends TestCase
{
    public function testDefaults(): void
    {
        $type = new ProjectType();

        self::assertNull($type->getName());
        self::assertSame('', (string) $type);
    }

    public function testAccessors(): void
    {
        $type = (new ProjectType())->setName('Drift');

        self::assertSame('Drift', $type->getName());
        self::assertSame('Drift', (string) $type);
    }
}
