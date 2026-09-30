<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * One row of a project's relevant links: the url plus an optional note saying
 * what it points to. Has no data_class, so a row maps to the plain array that
 * {@see \App\Entity\Project::setLinks()} stores.
 *
 * @extends AbstractType<array<string, string|null>>
 */
class ProjectLinkType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('url', UrlType::class, [
                'label' => false,
                'required' => false,
                'default_protocol' => 'https',
                'attr' => ['placeholder' => 'project.link_url_placeholder'],
                // Reject non-http(s) URLs (e.g. javascript:) to prevent stored XSS.
                'constraints' => [new Assert\Url(protocols: ['http', 'https'])],
            ])
            ->add('note', TextType::class, [
                'label' => false,
                'required' => false,
                'attr' => ['placeholder' => 'project.link_note_placeholder', 'maxlength' => 255],
                'constraints' => [new Assert\Length(max: 255)],
            ]);
    }
}
