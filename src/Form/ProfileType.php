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

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;

class ProfileType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('firstName', TextType::class, [
                'label' => false,
            ])
            ->add('lastName', TextType::class, [
                'label' => false,
            ])
            ->add('email', EmailType::class, [
                'label' => false,
            ])
            ->add('location', TextType::class, [
                'required' => false,
                'label' => false,
            ])
            ->add('socialLinks', CollectionType::class, [
                'entry_type' => SocialLinkEntryType::class,
                'entry_options' => [
                    'label' => false,
                ],
                'allow_add' => true,
                'allow_delete' => true,
                'prototype' => true,
                'by_reference' => false,
                'required' => false,
                'empty_data' => ['network' => 'custom', 'username' => ''],
            ]);
    }
}
