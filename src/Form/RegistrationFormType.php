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
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
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
            ])
            ->add('active', HiddenType::class)
            ->add('email', TextType::class, [
                'label' => 'label.email',
                'label_attr' => ['class' => 'form-label form-label-outside'],
            ])
            ->add('agreeTerms', CheckboxType::class, [
                'label' => 'label.agreeTerms',
                'label_attr' => ['class' => 'form-label form-label-outside form-check-label'],
                'attr' => ['class' => 'form-check-input'],
                'mapped' => false,
                'constraints' => [
                    new IsTrue([
                        'message' => 'You should agree to our terms.',
                    ]),
                ],
            ])
            ->add('password', RepeatedType::class, [
                'type' => PasswordType::class,
                'options' => ['attr' => ['class' => 'form-input form-input-circle form-input-gray'], 'label_attr' => ['class' => 'form-label form-label-outside']],
                'invalid_message' => 'The password fields must match.',
                'required' => true,
                'first_options' => ['label' => 'label.password'],
                'second_options' => ['label' => 'label.repeatPassword'],
                // 'mapped' => false,
                'constraints' => [
                    new NotBlank([
                        'message' => 'Please enter a password',
                    ]),
                    new Length([
                        'min' => 6,
                        'minMessage' => 'Your password should be at least {{ limit }} characters',
                        'max' => 4096,
                    ]),
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}
