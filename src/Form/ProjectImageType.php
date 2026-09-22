<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\ProjectImage;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @extends AbstractType<ProjectImage>
 */
class ProjectImageType extends AbstractType
{
    public function __construct(
        #[Autowire('%env(PROJECT_IMAGE_MAX_SIZE)%')]
        private readonly string $maxImageSize,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('imageFile', FileType::class, [
                'label' => 'project.image_file',
                'required' => false,
                'attr' => ['accept' => 'image/png,image/jpeg,image/gif'],
                'constraints' => [
                    new Assert\Image(maxSize: $this->maxImageSize),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ProjectImage::class,
        ]);
    }
}
