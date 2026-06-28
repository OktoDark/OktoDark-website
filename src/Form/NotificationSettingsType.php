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
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class NotificationSettingsType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        // Add individual checkbox fields for notificationPreferences array keys
        $builder
            ->add('blog_email', CheckboxType::class, [
                'required' => false,
                'property_path' => 'notificationPreferences[blog_email]',
                'label' => false,
            ])
            ->add('blog_onsite', CheckboxType::class, [
                'required' => false,
                'property_path' => 'notificationPreferences[blog_onsite]',
                'label' => false,
            ])
            ->add('forum_email', CheckboxType::class, [
                'required' => false,
                'property_path' => 'notificationPreferences[forum_email]',
                'label' => false,
            ])
            ->add('forum_onsite', CheckboxType::class, [
                'required' => false,
                'property_path' => 'notificationPreferences[forum_onsite]',
                'label' => false,
            ])
            ->add('follow_email', CheckboxType::class, [
                'required' => false,
                'property_path' => 'notificationPreferences[follow_email]',
                'label' => false,
            ])
            ->add('follow_onsite', CheckboxType::class, [
                'required' => false,
                'property_path' => 'notificationPreferences[follow_onsite]',
                'label' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}
