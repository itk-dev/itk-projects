<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use ITKDev\EntityBundle\Entity\AbstractITKDevEntity;
use ITKDev\EntityBundle\Entity\Contract\BlameableInterface;
use ITKDev\EntityBundle\Entity\Contract\TimestampableInterface;
use ITKDev\EntityBundle\Entity\Trait\BlameableTrait;
use ITKDev\EntityBundle\Entity\Trait\TimestampableTrait;

/**
 * Project base for persisted domain entities.
 *
 * Extends the bundle's {@see AbstractITKDevEntity} (ULID identity + the
 * #[ITKDevEntity] discovery marker) and composes the cross-cutting concerns the
 * application applies uniformly to its domain entities: created/updated
 * timestamps and created-by/modified-by blame, both populated on flush by the
 * bundle's listeners.
 *
 * The Vich-backed media entities ({@see ProjectImage}, {@see ProjectAttachment})
 * extend this base too; they reuse the timestampable "updatedAt" column as the
 * field Vich touches on upload rather than declaring their own.
 */
#[ORM\MappedSuperclass]
abstract class AbstractEntity extends AbstractITKDevEntity implements TimestampableInterface, BlameableInterface
{
    use TimestampableTrait;
    use BlameableTrait;
}
