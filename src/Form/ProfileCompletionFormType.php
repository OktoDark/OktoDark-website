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
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

class ProfileCompletionFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('firstName', TextType::class, [
                'label' => 'label.firstName',
                'constraints' => [
                    new NotBlank(message: 'error.firstname_blank'),
                ],
            ])
            ->add('lastName', TextType::class, [
                'label' => 'label.lastName',
                'constraints' => [
                    new NotBlank(message: 'error.lastname_blank'),
                ],
            ])
            ->add('location', TextType::class, [
                'label' => 'label.location',
                'required' => false,
            ])
            ->add('newsletter', CheckboxType::class, [
                'label' => 'label.newsletter',
                'required' => false,
            ])
            ->add('darkMode', CheckboxType::class, [
                'label' => 'label.darkMode',
                'required' => false,
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
