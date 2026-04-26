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
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class PreferencesType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('newsletter', CheckboxType::class, [
                'required' => false,
            ])
            ->add('darkMode', CheckboxType::class, [
                'required' => false,
            ])
            // Privacy Sub-form (JSON)
            ->add('privacy', FormType::class, [
                'inherit_data' => false,
                'label' => false,
            ]);

        $builder->get('privacy')
            ->add('profilePublic', CheckboxType::class, ['required' => false])
            ->add('showFirstName', CheckboxType::class, ['required' => false])
            ->add('showLastName', CheckboxType::class, ['required' => false])
            ->add('showEmail', CheckboxType::class, ['required' => false])
            ->add('showLocation', CheckboxType::class, ['required' => false])
            ->add('showSocialLinks', CheckboxType::class, ['required' => false])
            ->add('showRoles', CheckboxType::class, ['required' => false])
            ->add('showMemberSince', CheckboxType::class, ['required' => false])
            ->add('showAccountStatus', CheckboxType::class, ['required' => false]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}
