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

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\IsTrue;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class RegistrationFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('username', TextType::class, [
                'label' => 'label.username',
                'label_attr' => ['class' => 'form-label form-label-outside'],
                'required' => true,
                'constraints' => [
                    new NotBlank(message: 'error.username_blank'),
                ],
            ])

            // REMOVE THIS — it breaks the flow
            // ->add('active', HiddenType::class)

            ->add('email', TextType::class, [
                'label' => 'label.email',
                'label_attr' => ['class' => 'form-label form-label-outside'],
                'required' => true,
                'constraints' => [
                    new NotBlank(message: 'error.email_blank'),
                ],
            ])

            ->add('agreeTerms', CheckboxType::class, [
                'label' => 'label.agreeTerms',
                'label_attr' => ['class' => 'form-label form-label-outside form-check-label'],
                'attr' => ['class' => 'form-check-input'],
                'mapped' => false,
                'constraints' => [
                    new IsTrue(message: 'error.agree_terms'),
                ],
            ])

            ->add('password', RepeatedType::class, [
                'type' => PasswordType::class,
                'mapped' => false,
                'invalid_message' => 'error.password_mismatch',
                'required' => true,

                'first_options' => [
                    'label' => 'label.password',
                    'attr' => ['class' => 'modern-input'],
                    'label_attr' => ['class' => 'form-label form-label-outside'],
                    'constraints' => [
                        new NotBlank(message: 'error.password_blank'),
                        new Length(
                            min: 6,
                            max: 4096,
                            minMessage: 'error.password_min'
                        ),
                    ],
                ],

                'second_options' => [
                    'label' => 'label.repeatPassword',
                    'attr' => ['class' => 'modern-input'],
                    'label_attr' => ['class' => 'form-label form-label-outside'],
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
            'translation_domain' => 'labels',
        ]);
    }
}