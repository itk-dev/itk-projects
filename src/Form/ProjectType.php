<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Area;
use App\Entity\Department;
use App\Entity\Project;
use App\Enum\EndorsementAuthor;
use App\Enum\Funding;
use App\Enum\ProjectType as ProjectTypeEnum;
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
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

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
            ->add('description', TextareaType::class, [
                'label' => 'project.description',
                'required' => false,
                'attr' => ['rows' => 4],
                'help' => 'project.description_help',
            ])
            ->add('strategies', TermsTextType::class, [
                'label' => 'project.strategies',
                'vocabulary' => Vocabulary::Strategy,
                'required' => false,
                'help' => 'project.terms_help',
            ])
            ->add('projectType', EnumType::class, [
                'label' => 'project.project_type',
                'class' => ProjectTypeEnum::class,
                'required' => false,
                'placeholder' => 'form.choose',
                'choice_label' => static fn (ProjectTypeEnum $value): string => $value->labelKey(),
                'help' => 'project.project_type_help',
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
                'required' => false,
                'placeholder' => 'form.choose',
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
            ->add('budget', IntegerType::class, [
                'label' => 'project.budget',
                'required' => false,
                'attr' => ['min' => 0],
                'help' => 'project.budget_help',
            ])
            ->add('funding', EnumType::class, [
                'label' => 'project.funding',
                'class' => Funding::class,
                'multiple' => true,
                'expanded' => true,
                'required' => false,
                'choice_label' => static fn (Funding $value): string => $value->labelKey(),
            ])
            ->add('stakeholders', TermsTextType::class, [
                'label' => 'project.stakeholders',
                'vocabulary' => Vocabulary::Stakeholder,
                'required' => false,
                'help' => 'project.terms_help',
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
                'entry_type' => UrlType::class,
                'entry_options' => [
                    'required' => false,
                    'default_protocol' => 'https',
                    'label' => false,
                    // Reject non-http(s) URLs (e.g. javascript:) to prevent stored XSS.
                    'constraints' => [new Assert\Url(protocols: ['http', 'https'])],
                ],
                'allow_add' => true,
                'allow_delete' => true,
                'delete_empty' => true,
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
            ])
            ->add('images', CollectionType::class, [
                'label' => 'project.images',
                'entry_type' => ProjectImageType::class,
                'allow_add' => true,
                'allow_delete' => true,
                'by_reference' => false,
                'required' => false,
                'prototype' => true,
            ])
            ->add('attachments', CollectionType::class, [
                'label' => 'project.attachments',
                'entry_type' => ProjectAttachmentType::class,
                'allow_add' => true,
                'allow_delete' => true,
                'by_reference' => false,
                'required' => false,
                'prototype' => true,
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
