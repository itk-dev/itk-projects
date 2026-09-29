<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Contact;
use App\Form\DataTransformer\ContactsTextTransformer;
use App\Repository\ContactRepository;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * A text input mapping a comma-separated list of contact ids to a collection of
 * {@see Contact}s, picked from the shared pool or created on the fly.
 *
 * @extends AbstractType<mixed>
 */
final class ContactsTextType extends AbstractType
{
    public function __construct(
        private readonly ContactRepository $contactRepository,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->addModelTransformer(new ContactsTextTransformer($this->contactRepository));
    }

    /**
     * Expose the existing contacts as a searchable pool of {id, label} pairs —
     * keyed on id because people share names, labelled with the email to tell
     * them apart — plus the endpoint a typed name is created through. Both are
     * rendered as data attributes the Tom Select initialiser reads.
     */
    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        $pool = array_map(
            static fn (Contact $contact): array => [
                'id' => (string) $contact->getId(),
                'label' => $contact->getLabel(),
            ],
            $this->contactRepository->findAllOrdered(),
        );

        $view->vars['attr'] = array_merge($view->vars['attr'], [
            'data-contact-select' => '',
            'data-contact-pool' => json_encode($pool, \JSON_THROW_ON_ERROR),
            'data-contact-create-url' => $this->urlGenerator->generate('app_contact_create'),
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'invalid_message' => 'form.terms.invalid',
        ]);
    }

    public function getParent(): string
    {
        return TextType::class;
    }
}
