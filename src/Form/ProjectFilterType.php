<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Area;
use App\Entity\Department;
use App\Enum\ProjectType as ProjectTypeEnum;
use App\Enum\Status;
use App\Model\ProjectFilter;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\SearchType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<ProjectFilter>
 */
class ProjectFilterType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $boolChoiceValue = static fn (?bool $value): string => null === $value ? '' : ($value ? '1' : '0');

        $builder
            ->add('q', SearchType::class, [
                'label' => 'filter.search',
                'required' => false,
                'attr' => ['placeholder' => 'filter.search_placeholder'],
            ])
            ->add('status', EnumType::class, [
                'label' => 'project.status',
                'class' => Status::class,
                'required' => false,
                'placeholder' => 'filter.all',
                'choice_label' => static fn (Status $value): string => $value->labelKey(),
            ])
            ->add('area', EntityType::class, [
                'label' => 'project.area',
                'class' => Area::class,
                'choice_label' => 'name',
                'required' => false,
                'placeholder' => 'filter.all',
            ])
            ->add('projectType', EnumType::class, [
                'label' => 'project.project_type',
                'class' => ProjectTypeEnum::class,
                'required' => false,
                'placeholder' => 'filter.all',
                'choice_label' => static fn (ProjectTypeEnum $value): string => $value->labelKey(),
            ])
            ->add('organizationalAnchoring', EntityType::class, [
                'label' => 'project.organizational_anchoring',
                'class' => Department::class,
                'choice_label' => 'name',
                'required' => false,
                'placeholder' => 'filter.all',
            ])
            ->add('endorsement', ChoiceType::class, [
                'label' => 'project.endorsement',
                'required' => false,
                'placeholder' => 'filter.all',
                'choices' => ['filter.yes' => true, 'filter.no' => false],
                'choice_value' => $boolChoiceValue,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ProjectFilter::class,
            'method' => 'GET',
            'csrf_protection' => false,
            'required' => false,
            // The list also carries sort/direction/page query params that are
            // not form fields; ignore them instead of failing validation.
            'allow_extra_fields' => true,
        ]);
    }

    public function getBlockPrefix(): string
    {
        // Empty prefix keeps the query string clean (?status=…&q=…).
        return '';
    }
}
