<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Area;
use App\Entity\Department;
use App\Entity\Project;
use App\Entity\ProjectType as ProjectTypeEntity;
use App\Enum\EndorsementAuthor;
use App\Enum\Funding;
use App\Enum\FundingRate;
use App\Enum\Status;
use App\Enum\Vocabulary;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<Project>
 */
class ProjectType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'label' => 'project.title',
                'help' => 'project.title_help',
            ])
            ->add('topic', TextareaType::class, [
                'label' => 'project.topic',
                'required' => false,
                'attr' => ['rows' => 4],
                'help' => 'project.topic_help',
            ])
            ->add('area', EntityType::class, [
                'label' => 'project.area',
                'class' => Area::class,
                'choice_label' => 'name',
                'required' => false,
                'placeholder' => 'form.choose',
                'help' => 'project.area_help',
            ])
            ->add('summary', TextareaType::class, [
                'label' => 'project.summary',
                'required' => false,
                'attr' => ['rows' => 4],
                'help' => 'project.summary_help',
            ])
            ->add('description', TextareaType::class, [
                'label' => 'project.description',
                'required' => false,
                'attr' => ['rows' => 8],
                'help' => 'project.description_help',
            ])
            ->add('types', EntityType::class, [
                'label' => 'project.types',
                'class' => ProjectTypeEntity::class,
                'choice_label' => 'name',
                'multiple' => true,
                'required' => false,
                // Admin-managed pool, so a searchable multiselect without on-the-fly
                // creation, like the department field (Tom Select, see app.js).
                'attr' => ['data-type-select' => true, 'placeholder' => 'form.choose'],
                'help' => 'project.types_help',
            ])
            ->add('status', EnumType::class, [
                'label' => 'project.status',
                'class' => Status::class,
                'required' => false,
                'placeholder' => 'form.choose',
                'choice_label' => static fn (Status $value): string => $value->labelKey(),
                'help' => 'project.status_help',
            ])
            ->add('statusAdditional', TextareaType::class, [
                'label' => 'project.status_additional',
                'required' => false,
                'attr' => ['rows' => 3],
                'help' => 'project.status_additional_help',
            ])
            ->add('organizationalAnchoring', EntityType::class, [
                'label' => 'project.organizational_anchoring',
                'class' => Department::class,
                'choice_label' => 'name',
                'multiple' => true,
                'required' => false,
                // Departments are admin-managed, so the pool is fixed: a searchable
                // multiselect (Tom Select, see app.js) without on-the-fly creation.
                'attr' => ['data-department-select' => true, 'placeholder' => 'form.choose'],
                'help' => 'project.organizational_anchoring_help',
            ])
            ->add('endorsement', CheckboxType::class, [
                'label' => 'project.endorsement',
                'required' => false,
            ])
            ->add('endorsementAuthor', EnumType::class, [
                'label' => 'project.endorsement_author',
                'class' => EndorsementAuthor::class,
                'required' => false,
                'placeholder' => 'form.choose',
                'choice_label' => static fn (EndorsementAuthor $value): string => $value->labelKey(),
                'help' => 'project.endorsement_author_help',
            ])
            ->add('amountApplied', IntegerType::class, [
                'label' => 'project.amount_applied',
                'required' => false,
                'attr' => ['min' => 0],
                'help' => 'project.amount_applied_help',
            ])
            ->add('budget', IntegerType::class, [
                'label' => 'project.budget',
                'required' => false,
                'attr' => ['min' => 0],
                'help' => 'project.budget_help',
            ])
            ->add('budgetItk', IntegerType::class, [
                'label' => 'project.budget_itk',
                'required' => false,
                'attr' => ['min' => 0],
                'help' => 'project.budget_itk_help',
            ])
            ->add('fundingRate', EnumType::class, [
                'label' => 'project.funding_rate',
                'class' => FundingRate::class,
                'required' => false,
                'placeholder' => 'form.choose',
                'choice_label' => static fn (FundingRate $value): string => $value->labelKey(),
                'help' => 'project.funding_rate_help',
            ])
            ->add('coFinancing', CheckboxType::class, [
                'label' => 'project.co_financing',
                'required' => false,
            ])
            ->add('funding', EnumType::class, [
                'label' => 'project.funding',
                'class' => Funding::class,
                'multiple' => true,
                'expanded' => true,
                'required' => false,
                'choice_label' => static fn (Funding $value): string => $value->labelKey(),
            ])
            ->add('remainingFunding', TextareaType::class, [
                'label' => 'project.remaining_funding',
                'required' => false,
                'attr' => ['rows' => 3],
                'help' => 'project.remaining_funding_help',
            ])
            ->add('tags', TermsTextType::class, [
                'label' => 'project.tags',
                'vocabulary' => Vocabulary::Tag,
                'required' => false,
                'help' => 'project.terms_help',
            ])
            ->add('timePeriodStart', DateType::class, [
                'label' => 'project.time_period_start',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'required' => false,
                'help' => 'project.time_period_start_help',
            ])
            ->add('timePeriodEnd', DateType::class, [
                'label' => 'project.time_period_end',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'required' => false,
                'help' => 'project.time_period_end_help',
            ])
            ->add('links', CollectionType::class, [
                'label' => 'project.links',
                'entry_type' => ProjectLinkType::class,
                'entry_options' => ['label' => false],
                'allow_add' => true,
                'allow_delete' => true,
                // A row is a url plus a note, and only the url makes it a link: a
                // row holding just a note, or the untouched empty row, is dropped.
                'delete_empty' => static fn (?array $link): bool => '' === trim((string) ($link['url'] ?? '')),
                'by_reference' => false,
                'required' => false,
                'prototype' => true,
            ])
            ->add('contacts', ContactsTextType::class, [
                'label' => 'project.contacts',
                'required' => false,
                'help' => 'project.terms_help',
            ])
            ->add('partners', PartnersTextType::class, [
                'label' => 'project.partners',
                'required' => false,
                'help' => 'project.partners_help',
            ]);
    }

    /**
     * Flag the fields that count towards {@see Project::getCompletionPercentage()}
     * so the form theme can mark them. Keeps the list in one place.
     */
    public function finishView(FormView $view, FormInterface $form, array $options): void
    {
        foreach (Project::COMPLETION_FIELDS as $field) {
            if (isset($view[$field])) {
                $view[$field]->vars['completion_field'] = true;
            }
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Project::class,
        ]);
    }
}
