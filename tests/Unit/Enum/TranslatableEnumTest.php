<?php

declare(strict_types=1);

namespace App\Tests\Unit\Enum;

use App\Enum\EndorsementAuthor;
use App\Enum\Funding;
use App\Enum\ProjectType;
use App\Enum\Status;
use App\Enum\TranslatableEnum;
use App\Enum\Vocabulary;
use PHPUnit\Framework\TestCase;

final class TranslatableEnumTest extends TestCase
{
    public function testEveryCaseExposesAPrefixedLabelKey(): void
    {
        $this->assertLabelKeys(EndorsementAuthor::cases(), 'enum.endorsement_author.');
        $this->assertLabelKeys(Funding::cases(), 'enum.funding.');
        $this->assertLabelKeys(ProjectType::cases(), 'enum.project_type.');
        $this->assertLabelKeys(Status::cases(), 'enum.status.');
        $this->assertLabelKeys(Vocabulary::cases(), 'enum.vocabulary.');
    }

    /**
     * @param array<TranslatableEnum&\BackedEnum> $cases
     */
    private function assertLabelKeys(array $cases, string $prefix): void
    {
        self::assertNotEmpty($cases);

        foreach ($cases as $case) {
            self::assertSame($prefix.$case->value, $case->labelKey());
        }
    }
}
