<?php

/*
 * Copyright (c) OktoDark Studios
 * Website: https://www.oktodark.com
 *
 * Author: Razvan George H. (Viruzzz)
 *
 * For the full copyright and license information, please view the LICENSE.
 */

namespace App\Form;

use App\Entity\Contact;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

class ContactType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'required' => true,
                'label' => 'label.contact_name',
                'attr' => [
                    'class' => 'form-input form-input-circle form-input-gray',
                ],
                'constraints' => [
                    new NotBlank(),
                ],
            ])

            ->add('email', EmailType::class, [
                'required' => true,
                'label' => 'label.contact_email',
                'attr' => [
                    'class' => 'form-input form-input-circle form-input-gray',
                ],
                'constraints' => [
                    new NotBlank(),
                ],
            ])

            ->add('category', ChoiceType::class, [
                'label' => 'label.contact_category',
                'attr' => [
                    'class' => 'form-input form-input-circle form-input-gray',
                ],
                'choices' => [
                    'label.department_staff' => 'staff',
                    'label.department_webmaster' => 'webmaster',
                ],
                'required' => true,
                'placeholder' => 'label.choice_department',
                'empty_data' => null,
                'choice_translation_domain' => 'labels',
            ])

            ->add('subject', TextType::class, [
                'required' => true,
                'label' => 'label.contact_subject',
                'attr' => [
                    'class' => 'form-input form-input-circle form-input-gray',
                ],
                'constraints' => [
                    new NotBlank(),
                ],
            ])

            ->add('message', TextareaType::class, [
                'label' => 'label.contact_message',
                'attr' => [
                    'class' => 'form-input form-input-circle form-input-gray',
                ],
            ])

            ->add('submit', SubmitType::class, [
                'label' => 'label.send',
                'attr' => [
                    'class' => 'button button-block button-gradient button-circle contact-button-submit',
                    'style' => 'margin-top:20px; margin:auto; width:fit-content;',
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Contact::class,
            'translation_domain' => 'labels',
        ]);
    }
}
