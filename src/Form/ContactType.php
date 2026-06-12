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
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Blank;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class ContactType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $timestamp = time();

        $builder
            ->add('name', TextType::class, [
                'required' => true,
                'label' => 'label.contact_name',
                'attr' => [
                    'class' => 'form-input form-input-circle form-input-gray',
                    'maxlength' => 80,
                ],
                'constraints' => [
                    new NotBlank(),
                    new Length(min: 1, max: 80),
                ],
            ])

            ->add('email', EmailType::class, [
                'required' => true,
                'label' => 'label.contact_email',
                'attr' => [
                    'class' => 'form-input form-input-circle form-input-gray',
                    'maxlength' => 120,
                ],
                'constraints' => [
                    new NotBlank(),
                    new Length(min: 5, max: 120),
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
                    'label.department_services' => 'services',
                ],
                'required' => true,
                'placeholder' => 'label.choice_department',
                'empty_data' => null,
                'choice_translation_domain' => 'contact',
            ])

            ->add('subject', TextType::class, [
                'required' => true,
                'label' => 'label.contact_subject',
                'attr' => [
                    'class' => 'form-input form-input-circle form-input-gray',
                    'maxlength' => 120,
                ],
                'constraints' => [
                    new NotBlank(),
                    new Length(min: 3, max: 120),
                ],
            ])

            ->add('message', TextareaType::class, [
                'label' => 'label.contact_message',
                'attr' => [
                    'class' => 'form-input form-input-circle form-input-gray',
                    'maxlength' => 2000,
                ],
                'constraints' => [
                    new NotBlank(),
                    new Length(min: 10, max: 2000),
                ],
            ])

            ->add('captcha_answer', IntegerType::class, [
                'mapped' => false,
                'required' => true,
                'label' => 'label.captcha_question',
                'attr' => [
                    'class' => 'form-input form-input-circle form-input-gray no-spinner',
                    'placeholder' => 'Solve the math problem',
                ],
                'constraints' => [
                    new NotBlank(message: 'Please solve the math challenge.'),
                ],
            ])

            ->add('website', TextType::class, [
                'required' => false,
                'mapped' => false,
                'label' => false,
                'attr' => [
                    'style' => 'display:none !important;',
                    'tabindex' => '-1',
                    'autocomplete' => 'off',
                ],
                'constraints' => [
                    new Blank(message: 'This field must be empty.'),
                ],
            ])

            ->add('nickname', TextType::class, [
                'required' => false,
                'mapped' => false,
                'label' => false,
                'attr' => ['style' => 'display:none !important;'],
                'constraints' => [new Blank()],
            ])

            ->add('homepage', TextType::class, [
                'required' => false,
                'mapped' => false,
                'label' => false,
                'attr' => ['style' => 'display:none !important;'],
                'constraints' => [new Blank()],
            ])

            ->add('form_timestamp', HiddenType::class, [
                'mapped' => false,
                'data' => $timestamp,
            ])

            ->add('form_checksum', HiddenType::class, [
                'mapped' => false,
                'data' => hash('sha256', $timestamp.$_ENV['APP_SECRET']),
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
            'translation_domain' => 'contact',
            'csrf_protection' => true,
            'csrf_field_name' => '_token',
            'csrf_token_id' => 'contact_form',
        ]);
    }
}
