<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Contact;
use App\Entity\Department;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<Contact>
 */
class ContactType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'contact.name',
                'help' => 'contact.name_help',
            ])
            ->add('email', EmailType::class, [
                'label' => 'contact.email',
                'required' => false,
                'help' => 'contact.email_help',
            ])
            ->add('phone', TelType::class, [
                'label' => 'contact.phone',
                'required' => false,
                'help' => 'contact.phone_help',
            ])
            ->add('department', EntityType::class, [
                'placeholder' => 'contact.department_placeholder',
                'label' => 'contact.department',
                'class' => Department::class,
                'choice_label' => 'name',
                'required' => false,
                'help' => 'contact.department_help',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Contact::class,
        ]);
    }
}
