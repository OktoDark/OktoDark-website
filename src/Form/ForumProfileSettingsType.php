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
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ForumProfileSettingsType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('signature', TextareaType::class, [
                'required' => false,
                'label' => false,
                'attr' => [
                    'rows' => 4,
                    'placeholder' => 'Enter your forum signature...',
                ],
            ])
            ->add('forumSettings', FormType::class, [
                'label' => false,
            ]);

        $builder->get('forumSettings')
            ->add('showSignature', CheckboxType::class, ['required' => false])
            ->add('showOtherSignatures', CheckboxType::class, ['required' => false])
            ->add('stayHidden', CheckboxType::class, ['required' => false]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}
